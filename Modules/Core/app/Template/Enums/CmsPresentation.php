<?php

namespace Modules\Core\Template\Enums;

enum CmsPresentation: string
{
    case PublicPageStandard = 'public.page.standard';
    case PublicNavigationMenu = 'public.navigation.menu';
    case SystemAuthLogin = 'system.auth.login';
    case SystemAdminDashboard = 'system.admin.dashboard';
}
