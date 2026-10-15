<?php

return [
    'required' => ':attribute alanı zorunludur.',
    'string' => ':attribute metin olmalıdır.',
    'email' => 'Geçerli bir e-posta adresi girin.',
    'unique' => ':attribute daha önce eklenmiş.',
    'exists' => 'Bu e-posta ile kayıtlı kullanıcı bulunamadı.',
    'regex' => 'Geçerli bir domain girin (örnek: ornek.com).',
    'not_in' => 'Projenin kendi domain’i rakip olarak eklenemez.',
    'confirmed' => 'Parolalar eşleşmiyor.',
    'max' => ['string' => ':attribute en fazla :max karakter olabilir.'],
    'min' => ['string' => ':attribute en az :min karakter olmalıdır.'],
    'password' => ['mixed' => 'Parola büyük ve küçük harf içermelidir.', 'numbers' => 'Parola sayı içermelidir.', 'symbols' => 'Parola sembol içermelidir.', 'letters' => 'Parola harf içermelidir.'],
    'attributes' => ['name' => 'Ad', 'email' => 'E-posta', 'password' => 'Parola', 'domain' => 'Domain', 'competitorDomain' => 'Rakip domain'],
];
