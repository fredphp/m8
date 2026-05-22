<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Recharge\C2C2Agree;
use App\Admin\Actions\Recharge\C2CAgree;
use App\Admin\Actions\Recharge\C2CReject;
use App\Admin\Repositories\BankImport;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Controllers\AdminController;
use App\Admin\Actions\Recharge\Agree;
use App\Admin\Actions\Recharge\Reject;

class BankImportController extends AdminController
{

    protected $title= 'C2C充值记录';
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new BankImport(), function (Grid $grid) {
            $grid->model()->orderByDesc('id');
            $grid->column('id')->sortable();
            $grid->column('user_id');
            $grid->column('coin_name');
            $grid->column('number');
            $grid->column('zhe_number');
            $grid->column('type')->using([1=>'银行卡',2=> '二维码']);
            $grid->column('bank_name');
            $grid->column('bank_branch');
            $grid->column('bank_branch_code');
            $grid->column('bank_number');
            $grid->column('bank_username');
            $grid->column('qr_url');
            $grid->column('status')->using([0 => '待审核',1 =>'已审核',2=>'已付款',3 => '已完成',4 => '失败'])->filter(
                Grid\Column\Filter\In::make([0 => '待审核',1 =>'已审核',2=>'已付款',3 => '已完成',4 => '失败'])
            );
            $grid->column('created_at');
//            $grid->column('updated_at')->sortable();

            $grid->actions(function (Grid\Displayers\Actions $actions) {
//                $actions->disableDelete();
                $actions->disableEdit();
                $actions->disableQuickEdit();
                $actions->disableView();
                if ($this->status == 0){
                    $actions->append(new C2C2Agree());
                }
                 if ($this->status == 2) {
                    $actions->append(new C2CAgree());
                 }
//                $actions->append(new C2CReject());

            });
        
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
        return Show::make($id, new BankImport(), function (Show $show) {
            $show->field('id');
            $show->field('user_id');
            $show->field('coin_name');
            $show->field('number');
            $show->field('zhe_number');
            $show->field('type');
            $show->field('bank_name');
            $show->field('bank_branch');
            $show->field('bank_branch_code');
            $show->field('bank_number');
            $show->field('bank_username');
            $show->field('qr_url');
            $show->field('status');
            $show->field('created_at');
            $show->field('updated_at');
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(new BankImport(), function (Form $form) {
            $form->display('id');
            $form->text('user_id');
            $form->text('coin_name');
            $form->text('number');
            $form->text('zhe_number');
            $form->text('type');
            $form->text('bank_name');
            $form->text('bank_branch');
            $form->text('bank_branch_code');
            $form->text('bank_number');
            $form->text('bank_username');
            $form->text('qr_url');
            $form->text('status');
        
            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
