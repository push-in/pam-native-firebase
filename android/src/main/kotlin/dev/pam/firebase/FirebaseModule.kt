package dev.pam.firebase

import android.content.Context
import android.os.Bundle
import com.google.firebase.FirebaseApp
import com.google.firebase.FirebaseOptions
import com.google.firebase.analytics.FirebaseAnalytics
import com.google.firebase.crashlytics.FirebaseCrashlytics
import com.google.firebase.installations.FirebaseInstallations
import com.google.firebase.messaging.FirebaseMessaging
import com.google.firebase.remoteconfig.FirebaseRemoteConfig
import com.google.firebase.remoteconfig.FirebaseRemoteConfigFetchThrottledException
import com.google.firebase.remoteconfig.FirebaseRemoteConfigSettings
import dev.pam.nativeapp.modules.ModuleCompletion
import dev.pam.nativeapp.modules.ModuleResultStatus
import dev.pam.nativeapp.modules.NativeModule
import dev.pam.nativeapp.protocol.WireMap
import dev.pam.nativeapp.protocol.WireValue
import org.json.JSONObject

class FirebaseModule(
    context: Context,
) : NativeModule {
    private val applicationContext = context.applicationContext

    override fun invoke(method: String, payload: ByteArray, completion: ModuleCompletion) {
        runCatching { WireMap.decode(payload) }
            .onFailure { completion.failure(it) }
            .onSuccess { values ->
                when (method) {
                    "configure" -> configure(values, completion)
                    "analyticsLog" -> analyticsLog(values, completion)
                    "analyticsUserId" -> analyticsUserId(values, completion)
                    "analyticsUserProperty" -> analyticsUserProperty(values, completion)
                    "remoteFetch" -> remoteFetch(values, completion)
                    "remoteValues" -> remoteValues(values, completion)
                    "remoteDefaults" -> remoteDefaults(values, completion)
                    "messagingToken" -> messagingToken(completion)
                    "messagingDeleteToken" -> messagingDeleteToken(completion)
                    "installationId" -> installationId(values, completion)
                    "crashLog" -> crashLog(values, completion)
                    "crashRecord" -> crashRecord(values, completion)
                    else -> completion.failure(IllegalArgumentException("Unknown method: $method"))
                }
            }
    }

    private fun configure(values: Map<String, WireValue>, completion: ModuleCompletion) {
        runCatching {
            val name = values.text("name")
            FirebaseApp.getApps(applicationContext).firstOrNull { it.name == name }
                ?: FirebaseApp.initializeApp(
                    applicationContext,
                    FirebaseOptions.Builder()
                        .setApplicationId(values.text("applicationId"))
                        .setApiKey(values.text("apiKey"))
                        .apply {
                            values.optionalText("projectId")?.let(::setProjectId)
                            values.optionalText("senderId")?.let(::setGcmSenderId)
                            values.optionalText("storageBucket")?.let(::setStorageBucket)
                            values.optionalText("databaseUrl")?.let(::setDatabaseUrl)
                            values.optionalText("trackingId")?.let(::setGaTrackingId)
                        }
                        .build(),
                    name,
                )
        }.onSuccess { app ->
            completion.success(mapOf(
                "name" to WireValue.Text(app.name),
                "applicationId" to WireValue.Text(app.options.applicationId),
                "projectId" to WireValue.Text(app.options.projectId.orEmpty()),
            ))
        }.onFailure { error -> completion.failure(error) }
    }

    private fun analyticsLog(values: Map<String, WireValue>, completion: ModuleCompletion) {
        runCatching {
            val parameters = Bundle()
            values.filterKeys { it.startsWith("param_") }.forEach { (key, value) ->
                val name = key.removePrefix("param_")
                when (value) {
                    is WireValue.Text -> parameters.putString(name, value.value)
                    is WireValue.Integer -> parameters.putLong(name, value.value)
                    is WireValue.Decimal -> parameters.putDouble(name, value.value)
                    is WireValue.Flag -> parameters.putLong(name, if (value.value) 1 else 0)
                }
            }
            FirebaseAnalytics.getInstance(applicationContext).logEvent(values.text("event"), parameters)
        }.onSuccess { completion.success() }.onFailure { error -> completion.failure(error) }
    }

    private fun analyticsUserId(values: Map<String, WireValue>, completion: ModuleCompletion) {
        FirebaseAnalytics.getInstance(applicationContext)
            .setUserId(values.text("userId").ifEmpty { null })
        completion.success()
    }

    private fun analyticsUserProperty(values: Map<String, WireValue>, completion: ModuleCompletion) {
        FirebaseAnalytics.getInstance(applicationContext).setUserProperty(
            values.text("name"),
            values.text("value").ifEmpty { null },
        )
        completion.success()
    }

    private fun remoteFetch(values: Map<String, WireValue>, completion: ModuleCompletion) {
        runCatching {
            remoteConfig(values.app()).apply {
                setConfigSettingsAsync(
                    FirebaseRemoteConfigSettings.Builder()
                        .setMinimumFetchIntervalInSeconds(values.integer("minimumInterval"))
                        .build(),
                )
            }
        }.onFailure { error -> completion.failure(error) }.onSuccess { remote ->
            remote.fetchAndActivate().addOnCompleteListener { task ->
                when {
                    task.isSuccessful -> completion.success(mapOf(
                        "state" to WireValue.Integer(if (task.result == true) 1 else 2),
                        "changed" to WireValue.Flag(task.result == true),
                    ))
                    task.exception is FirebaseRemoteConfigFetchThrottledException -> completion.success(mapOf(
                        "state" to WireValue.Integer(3),
                        "changed" to WireValue.Flag(false),
                        "message" to WireValue.Text(task.exception?.message.orEmpty()),
                    ))
                    else -> completion.failure(task.exception ?: IllegalStateException("Remote Config fetch failed"))
                }
            }
        }
    }

    private fun remoteValues(values: Map<String, WireValue>, completion: ModuleCompletion) {
        runCatching {
            val json = JSONObject()
            remoteConfig(values.app()).all.toSortedMap().forEach { (key, value) ->
                json.put(key, value.asString())
            }
            json.toString()
        }.onSuccess { json ->
            completion.success(mapOf("json" to WireValue.Text(json)))
        }.onFailure { error -> completion.failure(error) }
    }

    private fun remoteDefaults(values: Map<String, WireValue>, completion: ModuleCompletion) {
        runCatching {
            val json = JSONObject(values.text("json"))
            buildMap<String, Any> {
                json.keys().forEach { key -> put(key, json.get(key)) }
            }
        }.onFailure { error -> completion.failure(error) }.onSuccess { defaults ->
            remoteConfig(values.app()).setDefaultsAsync(defaults).addOnCompleteListener { task ->
                if (task.isSuccessful) completion.success()
                else completion.failure(task.exception ?: IllegalStateException("Remote defaults failed"))
            }
        }
    }

    private fun messagingToken(completion: ModuleCompletion) {
        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
            if (task.isSuccessful) completion.success(mapOf("token" to WireValue.Text(task.result)))
            else completion.failure(task.exception ?: IllegalStateException("Messaging token failed"))
        }
    }

    private fun messagingDeleteToken(completion: ModuleCompletion) {
        FirebaseMessaging.getInstance().deleteToken().addOnCompleteListener { task ->
            if (task.isSuccessful) completion.success()
            else completion.failure(task.exception ?: IllegalStateException("Messaging token deletion failed"))
        }
    }

    private fun installationId(values: Map<String, WireValue>, completion: ModuleCompletion) {
        FirebaseInstallations.getInstance(values.app()).id.addOnCompleteListener { task ->
            if (task.isSuccessful) completion.success(mapOf("identifier" to WireValue.Text(task.result)))
            else completion.failure(task.exception ?: IllegalStateException("Installation ID failed"))
        }
    }

    private fun crashLog(values: Map<String, WireValue>, completion: ModuleCompletion) {
        FirebaseCrashlytics.getInstance().log(values.text("message"))
        completion.success()
    }

    private fun crashRecord(values: Map<String, WireValue>, completion: ModuleCompletion) {
        FirebaseCrashlytics.getInstance().recordException(
            PamFirebaseException(values.text("name"), values.text("message")),
        )
        completion.success()
    }

    private fun remoteConfig(app: FirebaseApp): FirebaseRemoteConfig = FirebaseRemoteConfig.getInstance(app)

    private fun Map<String, WireValue>.app(): FirebaseApp {
        val name = optionalText("app") ?: FirebaseApp.DEFAULT_APP_NAME
        return FirebaseApp.getInstance(name)
    }

    private fun Map<String, WireValue>.text(key: String): String =
        (get(key) as? WireValue.Text)?.value ?: error("$key is required")

    private fun Map<String, WireValue>.optionalText(key: String): String? =
        (get(key) as? WireValue.Text)?.value?.takeIf(String::isNotEmpty)

    private fun Map<String, WireValue>.integer(key: String): Long =
        (get(key) as? WireValue.Integer)?.value ?: error("$key is required")

    private fun ModuleCompletion.success(values: Map<String, WireValue> = emptyMap()) {
        complete(ModuleResultStatus.SUCCESS, WireMap.encode(values))
    }

    private fun ModuleCompletion.failure(error: Throwable) {
        complete(
            ModuleResultStatus.FAILURE,
            (error.message ?: error::class.java.simpleName).toByteArray(Charsets.UTF_8),
        )
    }
}

private class PamFirebaseException(
    private val firebaseName: String,
    message: String,
) : RuntimeException(message) {
    override fun toString(): String = "$firebaseName: $message"
}
