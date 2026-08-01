<?php

declare(strict_types=1);

namespace Pam\Native\Firebase;

enum RemoteFetchState: int
{
    case Activated = 1;
    case Fetched = 2;
    case Throttled = 3;
    case Failed = 4;
}
