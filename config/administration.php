<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Administrator Invitation
    |--------------------------------------------------------------------------
    |
    | Number of hours during which a first-administrator invitation remains
    | valid. The value can be adjusted per environment without changing code.
    |
    */

    'invitation_expiry_hours' => (int) env('ADMIN_INVITATION_EXPIRY_HOURS', 24),

];
