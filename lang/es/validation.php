<?php

// Mensajes de validación en español (los que usa AutoNexo).
return [
    'between' => [
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña es incorrecta.',
    'date' => 'El campo :attribute no es una fecha válida.',
    'decimal' => 'El campo :attribute debe tener :decimal decimales.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'exists' => 'El valor seleccionado en :attribute no es válido.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'gt' => ['numeric' => 'El campo :attribute debe ser mayor que :value.'],
    'gte' => ['numeric' => 'El campo :attribute debe ser mayor o igual que :value.'],
    'image' => 'El campo :attribute debe ser una imagen.',
    'in' => 'El valor seleccionado en :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'lowercase' => 'El campo :attribute debe estar en minúsculas.',
    'max' => [
        'file' => 'El archivo :attribute no debe pesar más de :max kilobytes.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe tener más de :max caracteres.',
    ],
    'mimes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'file' => 'El archivo :attribute debe pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'numeric' => 'El campo :attribute debe ser un número.',
    'password' => [
        'letters' => 'La :attribute debe tener al menos una letra.',
        'mixed' => 'La :attribute debe tener mayúsculas y minúsculas.',
        'numbers' => 'La :attribute debe tener al menos un número.',
        'symbols' => 'La :attribute debe tener al menos un símbolo.',
        'uncompromised' => 'La :attribute apareció en una filtración de datos. Elige otra.',
    ],
    'regex' => 'El formato de :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'unique' => 'Este :attribute ya está registrado.',
    'uploaded' => 'No se pudo subir :attribute.',

    'attributes' => [
        'name' => 'nombre',
        'email' => 'correo',
        'password' => 'contraseña',
        'current_password' => 'contraseña actual',
    ],
];
