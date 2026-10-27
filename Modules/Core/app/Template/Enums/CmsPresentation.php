<?php

namespace Modules\Core\Template\Enums;

enum CmsPresentation: string
{
    case PublicPageStandard = 'public.page.standard';
    case PublicNavigationMenu = 'public.navigation.menu';
    case PublicEmbedDefault = 'public.embed.default';
    case SystemAuthLogin = 'system.auth.login';
    case SystemAdminDashboard = 'system.admin.dashboard';
    case SystemAdminAudit = 'system.admin.audit';
    case SystemAdminPages = 'system.admin.pages';
    case SystemAdminPagesEdit = 'system.admin.pages.edit';
    case SystemAdminMenus = 'system.admin.menus';
}
