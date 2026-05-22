<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages.
    |
    */

    'accepted'        => 'يجب قبول :attribute.',
    'active_url'      => ':attribute ليس عنوان URL صالحًا.',
    'after'           => 'يجب أن يكون :attribute تاريخًا بعد :date.',
    'after_or_equal'  => 'يجب أن يكون :attribute تاريخًا بعد أو يساوي :date.',
    'alpha'           => 'يجب أن يحتوي :attribute على أحرف فقط.',
    'alpha_dash'      => 'يجب أن يحتوي :attribute على أحرف وأرقام وشرطات فقط.',
    'alpha_num'       => 'يجب أن يحتوي :attribute على أحرف وأرقام فقط.',
    'array'           => 'يجب أن يكون :attribute مصفوفة.',
    'before'          => 'يجب أن يكون :attribute تاريخًا قبل :date.',
    'before_or_equal' => 'يجب أن يكون :attribute تاريخًا قبل أو يساوي :date.',
    'between'         => [
        'numeric' => 'يجب أن يكون :attribute بين :min و :max.',
        'file'    => 'يجب أن يكون حجم :attribute بين :min و :max كيلوبايت.',
        'string'  => 'يجب أن يحتوي :attribute على :min إلى :max حرفًا.',
        'array'   => 'يجب أن يحتوي :attribute على :min إلى :max عنصرًا.',
    ],
    'boolean'        => 'يجب أن يكون حقل :attribute صحيحًا أو خاطئًا.',
    'confirmed'      => 'تأكيد :attribute لا يتطابق.',
    'date'           => ':attribute ليس تاريخًا صالحًا.',
    'date_equals'    => 'يجب أن يكون :attribute تاريخًا يساوي :date.',
    'date_format'    => ':attribute لا يتطابق مع الصيغة :format.',
    'different'      => 'يجب أن يكون :attribute و :other مختلفين.',
    'digits'         => 'يجب أن يحتوي :attribute على :digits أرقام.',
    'digits_between' => 'يجب أن يحتوي :attribute على :min إلى :max أرقام.',
    'dimensions'     => 'أبعاد الصورة :attribute غير صالحة.',
    'distinct'       => 'حقل :attribute يحتوي على قيمة مكررة.',
    'email'          => ':attribute ليس بريدًا إلكترونيًا صالحًا.',
    'ends_with'      => 'يجب أن ينتهي حقل :attribute بإحدى القيم التالية: :values.',
    'exists'         => ':attribute غير صالح.',
    'file'           => 'يجب أن يكون حقل :attribute ملفًا.',
    'filled'         => 'حقل :attribute مطلوب.',
    'gt'             => [
        'numeric' => 'يجب أن يكون حقل :attribute أكبر من :value.',
        'file'    => 'يجب أن يكون حجم :attribute أكبر من :value كيلوبايت.',
        'string'  => 'يجب أن يحتوي :attribute على أكثر من :value حرفًا.',
        'array'   => 'يجب أن يحتوي :attribute على أكثر من :value عنصرًا.',
    ],
    'gte' => [
        'numeric' => 'يجب أن يكون :attribute على الأقل :value.',
        'file'    => 'يجب أن يكون حجم :attribute على الأقل :value كيلوبايت.',
        'string'  => 'يجب أن يحتوي :attribute على الأقل :value حرفًا.',
        'array'   => 'يجب أن يحتوي :attribute على الأقل :value عنصرًا.',
    ],
    'image'    => 'يجب أن يكون :attribute صورة.',
    'in'       => ':attribute غير صالح.',
    'in_array' => 'حقل :attribute غير موجود في :other.',
    'integer'  => 'يجب أن يكون :attribute عددًا صحيحًا.',
    'ip'       => 'يجب أن يكون :attribute عنوان IP صالحًا.',
    'ipv4'     => 'يجب أن يكون :attribute عنوان IPv4 صالحًا.',
    'ipv6'     => 'يجب أن يكون :attribute عنوان IPv6 صالحًا.',
    'json'     => 'يجب أن يكون حقل :attribute سلسلة JSON صالحة.',
    'lt'       => [
        'numeric' => 'يجب أن يكون حقل :attribute أقل من :value.',
        'file'    => 'يجب أن يكون حجم :attribute أقل من :value كيلوبايت.',
        'string'  => 'يجب أن يحتوي :attribute على أقل من :value حرفًا.',
        'array'   => 'يجب أن يحتوي :attribute على أقل من :value عنصرًا.',
    ],
    'lte' => [
        'numeric' => 'يجب أن يكون :attribute على الأكثر :value.',
        'file'    => 'يجب أن يكون حجم :attribute على الأكثر :value كيلوبايت.',
        'string'  => 'يجب أن يحتوي :attribute على الأكثر :value حرفًا.',
        'array'   => 'يجب أن يحتوي :attribute على الأكثر :value عنصرًا.',
    ],
    'max' => [
        'numeric' => 'يجب ألا يكون :attribute أكبر من :max.',
        'file'    => 'يجب ألا يكون حجم :attribute أكبر من :max كيلوبايت.',
        'string'  => 'يجب ألا يحتوي :attribute على أكثر من :max حرفًا.',
        'array'   => 'يجب ألا يحتوي :attribute على أكثر من :max عنصرًا.',
    ],
    'mimes'     => 'يجب أن يكون :attribute ملفًا من النوع: :values.',
    'mimetypes' => 'يجب أن يكون :attribute ملفًا من النوع: :values.',
    'min'       => [
        'numeric' => 'يجب أن يكون حجم :attribute على الأقل :min.',
        'file'    => 'يجب أن يكون حجم :attribute على الأقل :min كيلوبايت.',
        'string'  => 'يجب أن يحتوي :attribute على الأقل :min حرفًا.',
        'array'   => 'يجب أن يحتوي :attribute على الأقل :min عنصرًا.',
    ],
    'not_in'               => ':attribute غير صالح.',
    'not_regex'            => 'صيغة حقل :attribute غير صالحة.',
    'numeric'              => 'يجب أن يكون :attribute عدديًا.',
    'password'             => 'كلمة المرور غير صحيحة.',
    'present'              => 'يجب أن يكون حقل :attribute موجودًا.',
    'regex'                => 'صيغة :attribute غير صالحة.',
    'required'             => 'حقل :attribute مطلوب.',
    'required_if'          => 'حقل :attribute مطلوب عندما يكون :other هو :value.',
    'required_unless'      => 'حقل :attribute مطلوب ما لم يكن :other في :values.',
    'required_with'        => 'حقل :attribute مطلوب عندما يكون :values موجودًا.',
    'required_with_all'    => 'حقل :attribute مطلوب عندما تكون :values موجودة.',
    'required_without'     => 'حقل :attribute مطلوب عندما لا يكون :values موجودًا.',
    'required_without_all' => 'حقل :attribute مطلوب عندما لا تكون أي من :values موجودة.',
    'same'                 => 'يجب أن يتطابق :attribute و :other.',
    'size'                 => [
        'numeric' => 'يجب أن يكون حجم :attribute :size.',
        'file'    => 'يجب أن يكون حجم :attribute :size كيلوبايت.',
        'string'  => 'يجب أن يحتوي :attribute على :size حرفًا.',
        'array'   => 'يجب أن يحتوي :attribute على :size عنصرًا.',
    ],
    'starts_with' => 'يجب أن يبدأ حقل :attribute بإحدى القيم التالية: :values.',
    'string'      => 'يجب أن يكون حقل :attribute سلسلة نصية.',
    'timezone'    => 'يجب أن يكون :attribute منطقة زمنية صالحة.',
    'unique'      => 'حقل :attribute تم تسجيله مسبقًا.',
    'uploaded'    => 'فشل رفع :attribute.',
    'url'         => 'صيغة :attribute غير صالحة.',
    'uuid'        => 'يجب أن يكون حقل :attribute UUID صالحًا.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'password' => [
            'min' => 'يجب أن تحتوي :attribute على أكثر من :min حرفًا.',
        ],
        'email' => [
            'unique' => 'تم تسجيل :attribute مسبقًا.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute place-holders
    | with something more reader friendly such as E-Mail Address instead
    | of "email". This simply helps us make messages a little cleaner.
    |
    */

    'attributes' => [
        'address'               => 'العنوان',
        'age'                   => 'العمر',
        'body'                  => 'المحتوى',
        'city'                  => 'المدينة',
        'content'               => 'المحتوى',
        'country'               => 'الدولة',
        'date'                  => 'التاريخ',
        'day'                   => 'اليوم',
        'description'           => 'الوصف',
        'email'                 => 'البريد الإلكتروني',
        'excerpt'               => 'المقتطف',
        'first_name'            => 'الاسم الأول',
        'gender'                => 'الجنس',
        'hour'                  => 'الساعة',
        'last_name'             => 'الاسم الأخير',
        'message'               => 'الرسالة',
        'minute'                => 'الدقيقة',
        'mobile'                => 'الهاتف المحمول',
        'month'                 => 'الشهر',
        'name'                  => 'الاسم',
        'password'              => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'payword' =>'كلمة المرور',
        'payword_confirmation' => 'تأكيد كلمة المرور',
        'phone'                 => 'الهاتف',
        'price'                 => 'السعر',
        'second'                => 'الثانية',
        'sex'                   => 'الجنس',
        'subject'               => 'الموضوع',
        'terms'                 => 'الشروط',
        'time'                  => 'الوقت',
        'title'                 => 'العنوان',
        'username'              => 'اسم المستخدم',
        'year'                  => 'السنة',
    ],
];
