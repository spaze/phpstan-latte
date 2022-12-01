<?php

declare(strict_types=1);

namespace Efabrica\PHPStanLatte\Tests\Rule\LatteTemplatesRule\PresenterWithoutModule\Fixtures;

final class LinksPresenter extends ParentPresenter
{
    public function actionDefault(): void
    {
    }

    public function actionCreate(): void
    {
    }

    public function actionEdit(string $id, int $sorting = 100): void
    {
    }

    public function actionPublish(string $id, int $sorting = 100, bool $isActive = true): void
    {
    }

    public function actionParamsMismatch(string $param1)
    {
    }

    public function renderParamsMismatch(string $param1, string $param2)
    {
    }

    public function actionArrayParam(array $ids, bool $option = false): void
    {
    }
}
