<?php
/*
 * @Author: your name
 * @Date: 2021-06-01 15:30:15
 * @LastEditTime: 2021-06-05 15:35:26
 * @LastEditors: Please set LastEditors
 * @Description: In User Settings Edit
 * @FilePath: \Dcat\app\Admin\Controllers\RechargeManualController.php
 */

namespace App\Admin\Controllers;

use App\Admin\Repositories\RechargeManualrg;
use App\Admin\Actions\Recharge\Agreerg;
use App\Admin\Actions\Recharge\Rejectrg;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Controllers\AdminController;

class RechargeManualrgController extends AdminController
{
    protected $translation = 'recharge-manualrg';
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
       // echo 11;die;
        return Grid::make(new RechargeManualrg(), function (Grid $grid) {
            $grid->model()->orderByDesc('id');
            $grid->model()->with(['user']);
           $grid->actions(function (Grid\Displayers\Actions $actions) {
                $actions->disableDelete();
                $actions->disableEdit();
                $actions->disableQuickEdit();
                $actions->disableView();
                 if ($this->status === 0) {
                $actions->append(new Agreerg());
                $actions->append(new Rejectrg());
                 }
            });
          //  $grid->enableDialogCreate();
            $grid->withBorder();
           // $grid->export();
            $grid->id->sortable();
            $grid->user_id;
            $grid->column('user.account', admin_trans_field('user_account'));
            $grid->account;
            $grid->cbsj;
            $grid->pay_currency;
           // $grid->jymm;
          //  $grid->account;
           // $grid->num;
           // $grid->image->image('', '50', '50');
            $grid->status->using([0 => '未处理', 1 => '审核通过', 2 => '驳回'])->badge([0 => 'primary', 1 => 'success', 2 => 'danger'])->filter(Grid\Column\Filter\In::make([0 => '未处理', 1 => '审核通过', '驳回']));;
            $grid->created_at->sortable();
            //$grid->updated_at->sortable();
            $grid->quickSearch(['id', 'uid'])->placeholder('搜索...');
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
        return Show::make($id, new RechargeManualrg(), function (Show $show) {
            $show->field('id');
            $show->field('uid');
            $show->field('account');
            $show->field('num');
            $show->field('image');
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
        return Form::make(new RechargeManualrg(), function (Form $form) {
            $form->display('id');
            $form->text('uid');
            $form->text('account');
            $form->text('num');
            $form->text('image');
            $form->text('status');

            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
