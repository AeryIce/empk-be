<?php

namespace App\Enums;

enum PanelRole: string
{
    case SuperModerator = 'super_moderator';
    case SuperAdmin = 'super_admin';
}
