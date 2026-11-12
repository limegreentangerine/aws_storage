<?php
namespace Concrete\Package\AwsStorage\Controller\SinglePage\Dashboard;

use Concrete\Core\Page\Controller\DashboardPageController;

class AwsStorage extends DashboardPageController
{
    public function view()
    {
        return $this->buildRedirect('/dsahboard/aws_storage/move_files');
    }
}
