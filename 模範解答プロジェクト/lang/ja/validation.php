<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Laravel 標準バリデーションメッセージの日本語化。
    | フォーム属性名のカスタマイズは各 FormRequest の `attributes()` で行う。
    |
    */

    'accepted' => ':attribute を承認してください。',
    'active_url' => ':attribute は有効な URL ではありません。',
    'after' => ':attribute は :date 以降の日付を指定してください。',
    'after_or_equal' => ':attribute は :date 以降の日付を指定してください。',
    'alpha' => ':attribute は英字のみで入力してください。',
    'alpha_dash' => ':attribute は英数字とハイフン、アンダースコアのみで入力してください。',
    'alpha_num' => ':attribute は英数字のみで入力してください。',
    'array' => ':attribute は配列で指定してください。',
    'before' => ':attribute は :date 以前の日付を指定してください。',
    'before_or_equal' => ':attribute は :date 以前の日付を指定してください。',
    'between' => [
        'numeric' => ':attribute は :min から :max の間で指定してください。',
        'file' => ':attribute は :min KB から :max KB の間のファイルを指定してください。',
        'string' => ':attribute は :min 文字から :max 文字で入力してください。',
        'array' => ':attribute は :min から :max 個の項目を指定してください。',
    ],
    'boolean' => ':attribute は true または false で指定してください。',
    'confirmed' => ':attribute と確認用の入力が一致しません。',
    'date' => ':attribute は有効な日付ではありません。',
    'date_equals' => ':attribute は :date と一致する日付を指定してください。',
    'date_format' => ':attribute は :format の形式で指定してください。',
    'declined' => ':attribute は拒否してください。',
    'different' => ':attribute と :other は異なる値を指定してください。',
    'digits' => ':attribute は :digits 桁で指定してください。',
    'digits_between' => ':attribute は :min 桁から :max 桁で指定してください。',
    'email' => ':attribute は有効なメールアドレス形式で入力してください。',
    'ends_with' => ':attribute は次のいずれかで終わる必要があります: :values',
    'exists' => '選択された :attribute は無効です。',
    'file' => ':attribute はファイルを指定してください。',
    'filled' => ':attribute を入力してください。',
    'gt' => [
        'numeric' => ':attribute は :value より大きい値を指定してください。',
        'file' => ':attribute は :value KB より大きいファイルを指定してください。',
        'string' => ':attribute は :value 文字より長く入力してください。',
        'array' => ':attribute は :value 個より多い項目を指定してください。',
    ],
    'gte' => [
        'numeric' => ':attribute は :value 以上の値を指定してください。',
        'file' => ':attribute は :value KB 以上のファイルを指定してください。',
        'string' => ':attribute は :value 文字以上で入力してください。',
        'array' => ':attribute は :value 個以上の項目を指定してください。',
    ],
    'image' => ':attribute は画像ファイルを指定してください。',
    'in' => '選択された :attribute は無効です。',
    'in_array' => ':attribute は :other に存在する値を指定してください。',
    'integer' => ':attribute は整数で指定してください。',
    'ip' => ':attribute は有効な IP アドレスを指定してください。',
    'ipv4' => ':attribute は有効な IPv4 アドレスを指定してください。',
    'ipv6' => ':attribute は有効な IPv6 アドレスを指定してください。',
    'json' => ':attribute は有効な JSON 文字列を指定してください。',
    'lt' => [
        'numeric' => ':attribute は :value より小さい値を指定してください。',
        'file' => ':attribute は :value KB より小さいファイルを指定してください。',
        'string' => ':attribute は :value 文字より短く入力してください。',
        'array' => ':attribute は :value 個より少ない項目を指定してください。',
    ],
    'lte' => [
        'numeric' => ':attribute は :value 以下の値を指定してください。',
        'file' => ':attribute は :value KB 以下のファイルを指定してください。',
        'string' => ':attribute は :value 文字以内で入力してください。',
        'array' => ':attribute は :value 個以下の項目を指定してください。',
    ],
    'max' => [
        'numeric' => ':attribute は :max 以下の値を指定してください。',
        'file' => ':attribute は :max KB 以下のファイルを指定してください。',
        'string' => ':attribute は :max 文字以内で入力してください。',
        'array' => ':attribute は :max 個以下の項目を指定してください。',
    ],
    'mimes' => ':attribute は次のファイル形式を指定してください: :values',
    'mimetypes' => ':attribute は次のファイル形式を指定してください: :values',
    'min' => [
        'numeric' => ':attribute は :min 以上の値を指定してください。',
        'file' => ':attribute は :min KB 以上のファイルを指定してください。',
        'string' => ':attribute は :min 文字以上で入力してください。',
        'array' => ':attribute は :min 個以上の項目を指定してください。',
    ],
    'not_in' => '選択された :attribute は無効です。',
    'not_regex' => ':attribute の形式が正しくありません。',
    'numeric' => ':attribute は数値で指定してください。',
    'password' => 'パスワードが正しくありません。',
    'present' => ':attribute フィールドを送信してください。',
    'regex' => ':attribute の形式が正しくありません。',
    'required' => ':attribute を入力してください。',
    'required_if' => ':other が :value の場合、:attribute を入力してください。',
    'required_unless' => ':other が :values 以外の場合、:attribute を入力してください。',
    'required_with' => ':values を指定する場合、:attribute も入力してください。',
    'required_with_all' => ':values を指定する場合、:attribute も入力してください。',
    'required_without' => ':values を指定しない場合、:attribute を入力してください。',
    'required_without_all' => ':values をどれも指定しない場合、:attribute を入力してください。',
    'same' => ':attribute と :other が一致しません。',
    'size' => [
        'numeric' => ':attribute は :size を指定してください。',
        'file' => ':attribute は :size KB のファイルを指定してください。',
        'string' => ':attribute は :size 文字で入力してください。',
        'array' => ':attribute は :size 個の項目を指定してください。',
    ],
    'starts_with' => ':attribute は次のいずれかで始まる必要があります: :values',
    'string' => ':attribute は文字列で指定してください。',
    'timezone' => ':attribute は有効なタイムゾーンを指定してください。',
    'unique' => ':attribute は既に使用されています。',
    'uploaded' => ':attribute をアップロードできませんでした。',
    'ulid' => ':attribute は有効な ULID 形式で指定してください。',
    'url' => ':attribute の形式が正しくありません。',
    'uuid' => ':attribute は有効な UUID 形式で指定してください。',

    'custom' => [],

    'attributes' => [],
];
