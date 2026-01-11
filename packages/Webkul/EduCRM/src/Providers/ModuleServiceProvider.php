<?php

namespace Webkul\EduCRM\Providers;

use Konekt\Concord\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        \Webkul\EduCRM\Models\Program::class,
        \Webkul\EduCRM\Models\Cohort::class,
        \Webkul\EduCRM\Models\LeadExtension::class,
        \Webkul\EduCRM\Models\QualificationRule::class,
        \Webkul\EduCRM\Models\NegativeKeyword::class,
        \Webkul\EduCRM\Models\LeadQualification::class,
        \Webkul\EduCRM\Models\PaymentPlan::class,
        \Webkul\EduCRM\Models\StudentPayment::class,
        \Webkul\EduCRM\Models\PaymentInstallment::class,
        \Webkul\EduCRM\Models\PaymentTransaction::class,
        \Webkul\EduCRM\Models\ScheduledCall::class,
        \Webkul\EduCRM\Models\UserAssignment::class,
        \Webkul\EduCRM\Models\NotificationLog::class,
        \Webkul\EduCRM\Models\NotificationTemplate::class,
        \Webkul\EduCRM\Models\StatusAutomation::class,
        \Webkul\EduCRM\Models\TrashLead::class,
        \Webkul\EduCRM\Models\AutomationActionType::class,
        \Webkul\EduCRM\Models\LeadMergeLog::class,
    ];
}
