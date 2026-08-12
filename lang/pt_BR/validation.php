<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Validation Messages (pt_BR)
|--------------------------------------------------------------------------
|
| Laravel ships validation messages in English only, and the application runs
| with APP_LOCALE=pt_BR, so without this file the panel renders the raw key
| ("validation.unique") to the operator instead of a sentence.
|
| Only the rules the admin forms can actually emit are translated here.
| APP_FALLBACK_LOCALE is set to "en" so any rule not listed degrades to the
| framework's English message rather than to a key.
|
*/

return [

    'array' => 'O campo :attribute deve ser uma lista.',
    'email' => 'O campo :attribute deve ser um e-mail válido.',
    'file' => 'O campo :attribute deve ser um arquivo.',
    'image' => 'O campo :attribute deve ser uma imagem.',
    'in' => 'O valor selecionado para :attribute é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'mimetypes' => 'O campo :attribute deve ser um arquivo do tipo: :values.',
    'numeric' => 'O campo :attribute deve ser um número.',
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser um texto.',
    'unique' => 'O valor informado em :attribute já está em uso.',

    'max' => [
        'array' => 'O campo :attribute não pode ter mais que :max itens.',
        'file' => 'O campo :attribute não pode ser maior que :max kilobytes.',
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais que :max caracteres.',
    ],

    'min' => [
        'array' => 'O campo :attribute deve ter ao menos :min itens.',
        'file' => 'O campo :attribute deve ter ao menos :min kilobytes.',
        'numeric' => 'O campo :attribute deve ser no mínimo :min.',
        'string' => 'O campo :attribute deve ter ao menos :min caracteres.',
    ],

    'password' => [
        'letters' => 'A :attribute deve conter ao menos uma letra.',
        'mixed' => 'A :attribute deve conter ao menos uma letra maiúscula e uma minúscula.',
        'numbers' => 'A :attribute deve conter ao menos um número.',
        'symbols' => 'A :attribute deve conter ao menos um símbolo.',
        'uncompromised' => 'A :attribute apareceu em um vazamento de dados. Escolha outra.',
    ],

    'attributes' => [],

];
