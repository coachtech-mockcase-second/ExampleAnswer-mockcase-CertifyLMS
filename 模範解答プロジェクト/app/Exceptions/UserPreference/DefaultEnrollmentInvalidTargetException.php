<?php

declare(strict_types=1);

namespace App\Exceptions\UserPreference;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * デフォルト資格に設定できない受講登録(status = 学習中止)を指定された際に投げる例外。
 *
 * 学習中 / 修了 の受講登録はデフォルト指定可。学習中止 はサイドバー Switcher やフォールバック UI から
 * 誤って選ばれた場合に本例外で 422 を返し、Handler によって元画面へのリダイレクト + flash error に変換される。
 *
 * SoftDelete 済 受講登録は Route Model Binding で先に 404 になるため、本例外では検出しない。
 */
final class DefaultEnrollmentInvalidTargetException extends UnprocessableEntityHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('不合格状態の資格はデフォルトに設定できません。', $previous);
    }
}
