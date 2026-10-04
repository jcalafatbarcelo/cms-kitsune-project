<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminPresentation;
use Illuminate\Http\Response;
use Modules\Core\Template\Enums\CmsPresentation;

class DashboardController extends Controller
{
    public function __invoke(AdminPresentation $presentation): Response
    {
        return $presentation->render(CmsPresentation::SystemAdminDashboard);
    }
}
