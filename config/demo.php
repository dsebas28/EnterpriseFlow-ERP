<?php

/*
|--------------------------------------------------------------------------
| Demo
|--------------------------------------------------------------------------
|
| The demo accounts created by DemoSeeder. With DEMO_MODE=true the sign-in
| page lists them so visitors can try every role in one click. Keep it off
| on any real deployment: it advertises working credentials.
|
*/

return [

    'enabled' => (bool) env('DEMO_MODE', false),

    'password' => 'password',

    // role => account. Roles are SystemRole values.
    'accounts' => [
        'owner' => ['name' => 'Sofía Ramírez', 'email' => 'owner@demo.test', 'summary' => 'Everything, in both demo companies'],
        'administrator' => ['name' => 'Daniel Ortega', 'email' => 'admin@demo.test', 'summary' => 'Everything except managing owners'],
        'manager' => ['name' => 'Laura Méndez', 'email' => 'manager@demo.test', 'summary' => 'Catalog, stock, purchasing, sales and reports'],
        'accountant' => ['name' => 'Carlos Rivas', 'email' => 'accountant@demo.test', 'summary' => 'Invoices, payments, expenses and the audit trail'],
        'sales' => ['name' => 'Valentina Cruz', 'email' => 'sales@demo.test', 'summary' => 'Customers, sales, invoicing and collections'],
        'warehouse' => ['name' => 'Andrés Molina', 'email' => 'warehouse@demo.test', 'summary' => 'Stock, transfers and receiving purchases'],
        'employee' => ['name' => 'Camila Torres', 'email' => 'employee@demo.test', 'summary' => 'Read-only: products, customers, stock and sales'],
    ],

];
