<?php

return [
    'admin_route_prefix' => 'formbuilder',
    'route_prefix' => 'formbuilder',
    'admin_middleware' => ['web', 'auth', 'verified', 'permission:settings.quotation_form.manage'],
    'form_middleware' => ['web', 'auth', 'verified', 'permission:leads.quotation.manage'],
    'layout' => 'layouts.ogformbuilder',
];
