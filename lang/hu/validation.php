<?php

return [
    'enum' => 'A(z) :attribute értéke érvénytelen.',
    'integer' => 'A(z) :attribute egész szám legyen.',
    'required' => 'A(z) :attribute megadása kötelező.',
    'string' => 'A(z) :attribute szöveg legyen.',
    'email' => 'A(z) :attribute érvényes emailcím legyen.',
    'confirmed' => 'A(z) :attribute megerősítése nem egyezik.',
    'unique' => 'A(z) :attribute már használatban van.',
    'min' => ['numeric' => 'A(z) :attribute legalább :min legyen.', 'string' => 'A(z) :attribute legalább :min karakter legyen.'],
    'max' => ['numeric' => 'A(z) :attribute legfeljebb :max lehet.', 'string' => 'A(z) :attribute legfeljebb :max karakter lehet.'],
    'attributes' => [
        'name' => 'név', 'email' => 'emailcím', 'password' => 'jelszó',
        'approval_status' => 'jóváhagyási állapot', 'per_page' => 'oldalméret', 'page' => 'oldalszám',
        'password_confirmation' => 'jelszó megerősítése', 'token' => 'visszaállító kód',
    ],
];
