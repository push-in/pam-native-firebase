<?php

declare(strict_types=1);

namespace Pam\Native\Firebase;

enum FirebaseOperationState: int
{
    case Succeeded = 1;
    case Failed = 2;
}
