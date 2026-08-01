import FirebaseAnalytics
import FirebaseCore
import FirebaseCrashlytics
import FirebaseInstallations
import FirebaseMessaging
import FirebaseRemoteConfig
import Foundation
import PamNative

public final class FirebaseModule: NativeModule, @unchecked Sendable {
    public init() {}

    public func invoke(method: String, payload: Data, completion: @escaping ModuleCompletion) {
        do {
            let values = try WireMap.decode(payload)
            switch method {
            case "configure": try configure(values, completion: completion)
            case "analyticsLog": try analyticsLog(values, completion: completion)
            case "analyticsUserId": try analyticsUserId(values, completion: completion)
            case "analyticsUserProperty": try analyticsUserProperty(values, completion: completion)
            case "remoteFetch": try remoteFetch(values, completion: completion)
            case "remoteValues": try remoteValues(values, completion: completion)
            case "remoteDefaults": try remoteDefaults(values, completion: completion)
            case "messagingToken": messagingToken(completion: completion)
            case "messagingDeleteToken": messagingDeleteToken(completion: completion)
            case "installationId": try installationId(values, completion: completion)
            case "crashLog": try crashLog(values, completion: completion)
            case "crashRecord": try crashRecord(values, completion: completion)
            default: failure("Unknown method: \(method)", completion: completion)
            }
        } catch {
            failure(String(describing: error), completion: completion)
        }
    }

    private func configure(_ values: [String: WireValue], completion: ModuleCompletion) throws {
        let name = try values.text("name")
        if let app = FirebaseApp.app(name: name) {
            try success([
                "name": .text(app.name),
                "applicationId": .text(app.options.googleAppID),
                "projectId": .text(app.options.projectID ?? ""),
            ], completion: completion)
            return
        }
        let options = FirebaseOptions(
            googleAppID: try values.text("applicationId"),
            gcmSenderID: values.optionalText("senderId") ?? ""
        )
        options.apiKey = try values.text("apiKey")
        options.projectID = values.optionalText("projectId")
        options.storageBucket = values.optionalText("storageBucket")
        options.databaseURL = values.optionalText("databaseUrl")
        FirebaseApp.configure(name: name, options: options)
        guard let app = FirebaseApp.app(name: name) else {
            throw FirebaseBridgeError.configurationFailed
        }
        try success([
            "name": .text(app.name),
            "applicationId": .text(app.options.googleAppID),
            "projectId": .text(app.options.projectID ?? ""),
        ], completion: completion)
    }

    private func analyticsLog(_ values: [String: WireValue], completion: ModuleCompletion) throws {
        var parameters: [String: Any] = [:]
        for (key, value) in values where key.hasPrefix("param_") {
            let parameter = String(key.dropFirst(6))
            switch value {
            case let .text(value): parameters[parameter] = value
            case let .integer(value): parameters[parameter] = value
            case let .decimal(value): parameters[parameter] = value
            case let .flag(value): parameters[parameter] = value ? 1 : 0
            }
        }
        Analytics.logEvent(try values.text("event"), parameters: parameters)
        try success(completion: completion)
    }

    private func analyticsUserId(_ values: [String: WireValue], completion: ModuleCompletion) throws {
        let value = try values.text("userId")
        Analytics.setUserID(value.isEmpty ? nil : value)
        try success(completion: completion)
    }

    private func analyticsUserProperty(_ values: [String: WireValue], completion: ModuleCompletion) throws {
        let value = try values.text("value")
        Analytics.setUserProperty(value.isEmpty ? nil : value, forName: try values.text("name"))
        try success(completion: completion)
    }

    private func remoteFetch(_ values: [String: WireValue], completion: @escaping ModuleCompletion) throws {
        let remote = try remoteConfig(values)
        let settings = RemoteConfigSettings()
        settings.minimumFetchInterval = TimeInterval(try values.integer("minimumInterval"))
        remote.configSettings = settings
        remote.fetchAndActivate { status, error in
            if let error {
                let state: Int64 = (error as NSError).code == 8002 ? 3 : 4
                try? self.success([
                    "state": .integer(state),
                    "changed": .flag(false),
                    "message": .text(error.localizedDescription),
                ], completion: completion)
                return
            }
            let changed = status == .successFetchedFromRemote
            try? self.success([
                "state": .integer(changed ? 1 : 2),
                "changed": .flag(changed),
            ], completion: completion)
        }
    }

    private func remoteValues(_ values: [String: WireValue], completion: ModuleCompletion) throws {
        let remote = try remoteConfig(values)
        let keys = Set(remote.allKeys(from: .remote) + remote.allKeys(from: .default))
        let dictionary = Dictionary(uniqueKeysWithValues: keys.sorted().map {
            ($0, remote.configValue(forKey: $0).stringValue)
        })
        let data = try JSONSerialization.data(withJSONObject: dictionary, options: [.sortedKeys])
        try success(["json": .text(String(decoding: data, as: UTF8.self))], completion: completion)
    }

    private func remoteDefaults(_ values: [String: WireValue], completion: ModuleCompletion) throws {
        let data = Data(try values.text("json").utf8)
        guard let dictionary = try JSONSerialization.jsonObject(with: data) as? [String: NSObject] else {
            throw FirebaseBridgeError.invalidDefaults
        }
        try remoteConfig(values).setDefaults(dictionary)
        try success(completion: completion)
    }

    private func messagingToken(completion: @escaping ModuleCompletion) {
        Messaging.messaging().token { token, error in
            if let error {
                self.failure(error.localizedDescription, completion: completion)
            } else {
                try? self.success(["token": .text(token ?? "")], completion: completion)
            }
        }
    }

    private func messagingDeleteToken(completion: @escaping ModuleCompletion) {
        Messaging.messaging().deleteToken { error in
            if let error {
                self.failure(error.localizedDescription, completion: completion)
            } else {
                try? self.success(completion: completion)
            }
        }
    }

    private func installationId(_ values: [String: WireValue], completion: @escaping ModuleCompletion) throws {
        Installations.installations(app: try app(values)).installationID { identifier, error in
            if let error {
                self.failure(error.localizedDescription, completion: completion)
            } else {
                try? self.success(["identifier": .text(identifier ?? "")], completion: completion)
            }
        }
    }

    private func crashLog(_ values: [String: WireValue], completion: ModuleCompletion) throws {
        Crashlytics.crashlytics().log(try values.text("message"))
        try success(completion: completion)
    }

    private func crashRecord(_ values: [String: WireValue], completion: ModuleCompletion) throws {
        let error = NSError(
            domain: try values.text("name"),
            code: 1,
            userInfo: [NSLocalizedDescriptionKey: try values.text("message")]
        )
        Crashlytics.crashlytics().record(error: error)
        try success(completion: completion)
    }

    private func app(_ values: [String: WireValue]) throws -> FirebaseApp {
        let name = values.optionalText("app") ?? "[DEFAULT]"
        guard let app = FirebaseApp.app(name: name) else { throw FirebaseBridgeError.appNotConfigured }
        return app
    }

    private func remoteConfig(_ values: [String: WireValue]) throws -> RemoteConfig {
        RemoteConfig.remoteConfig(app: try app(values))
    }

    private func success(
        _ values: [String: WireValue] = [:],
        completion: ModuleCompletion
    ) throws {
        completion(.success, try WireMap.encode(values))
    }

    private func failure(_ message: String, completion: ModuleCompletion) {
        completion(.failure, Data(message.utf8))
    }
}

private extension Dictionary where Key == String, Value == WireValue {
    func text(_ key: String) throws -> String {
        guard case let .text(value)? = self[key] else { throw FirebaseBridgeError.missingValue(key) }
        return value
    }

    func optionalText(_ key: String) -> String? {
        guard case let .text(value)? = self[key], !value.isEmpty else { return nil }
        return value
    }

    func integer(_ key: String) throws -> Int64 {
        guard case let .integer(value)? = self[key] else { throw FirebaseBridgeError.missingValue(key) }
        return value
    }
}

private enum FirebaseBridgeError: Error {
    case appNotConfigured
    case configurationFailed
    case invalidDefaults
    case missingValue(String)
}
