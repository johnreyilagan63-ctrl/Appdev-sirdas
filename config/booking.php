<?php

return [
    'entrance_fee_per_adult' => 100.00,
    'max_stay_nights' => 30,
    'max_photo_bytes' => 5 * 1024 * 1024,
    'login_max_attempts' => 5,
    'login_lock_minutes' => 15,
    'reset_code_max_attempts' => 5,
    'reset_code_expiry_minutes' => 10,
    'password_reset_token_minutes' => 15,
    'account_deletion_max_attempts' => 3,
    'account_deletion_lock_minutes' => 15,
    'upload_url' => '/uploads',
    'upload_dir' => public_path('uploads'),
    'mail_from' => env('MAIL_FROM_ADDRESS', env('MAIL_FROM', 'no-reply@oliyettebook.local')),
    'mail_driver' => env('MAIL_MAILER', env('MAIL_DRIVER', 'log')),
    'mail_log' => storage_path('logs/mail.log'),
];
