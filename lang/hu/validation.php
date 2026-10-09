<?php

return [
    'required' => 'A(z) :attribute megadása kötelező.',
    'string' => 'A(z) :attribute szöveg legyen.',
    'email' => 'A(z) :attribute érvényes emailcím legyen.',
    'confirmed' => 'A(z) :attribute megerősítése nem egyezik.',
    'unique' => 'A(z) :attribute már használatban van.',
    'min' => ['string' => 'A(z) :attribute legalább :min karakter legyen.'],
    'max' => ['string' => 'A(z) :attribute legfeljebb :max karakter lehet.'],
    'attributes' => [
        'name' => 'név', 'email' => 'emailcím', 'password' => 'jelszó',
        'password_confirmation' => 'jelszó megerősítése', 'token' => 'visszaállító kód',
    ],
];
