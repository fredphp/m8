<?php

namespace App\Admin\Controllers;

use App\Admin\Repositories\BankConfig;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Controllers\AdminController;

class BankConfigController extends AdminController
{
    protected $title= 'C2C收款配置';
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new BankConfig(), function (Grid $grid) {
            $grid->column('id')->sortable();
            $grid->column('bank_name',__('银行名称'));
            $grid->column('bank_branch','银行支行');
            $grid->column('bank_branch_code','支行号码');
            $grid->column('bank_number','银行卡号');
            $grid->column('bank_username','开户名');
            $grid->column('qr_url','收款码')->image();
            $grid->column('u_ja','U兑USDT比例')->editable();

            $grid->disableCreateButton();
            $grid->disableQuickEditButton();
//            $grid->disableEditButton();
            $grid->disableViewButton();
            $grid->disableBatchDelete();
            $grid->disableDeleteButton();
//            $grid->disableActions();
        
            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id');
        
            });
        });
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     *
     * @return Show
     */
    protected function detail($id)
    {
        return Show::make($id, new BankConfig(), function (Show $show) {
            $show->field('id');
            $show->field('bank_name');
            $show->field('bank_branch');
            $show->field('bank_branch_code');
            $show->field('bank_number');
            $show->field('bank_username');
            $show->field('qr_url')->image();
            $show->field('u_ja');
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(new BankConfig(), function (Form $form) {
            $form->display('id');
            $form->text('bank_name');
            $form->text('bank_branch');
            $form->text('bank_branch_code');
            $form->text('bank_number');
            $form->text('bank_username');
            $form->image('qr_url');
            $form->text('u_ja');
        });
    }
}
