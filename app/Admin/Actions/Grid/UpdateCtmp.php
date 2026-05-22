<?php

namespace App\Admin\Actions\Grid;

use App\Admin\Forms\UpdateContract;
use Dcat\Admin\Grid\RowAction;
use Dcat\Admin\Widgets\Modal;


class UpdateCtmp extends RowAction
{
    /**
     * @return string
     */
	protected $title = '修改';

    public function render()
    {
        // 实例化表单类并传递自定义参数
        $form = UpdateContract::make()->payload(['id' => $this->getKey()]);

        return Modal::make()
            ->lg()
            ->title($this->title)
            ->body($form)
            ->button($this->title);
    }
}
