<?php

namespace App\Admin\Controllers;

use App\Admin\Repositories\FollowPlan;
use App\Models\User;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Controllers\AdminController;

class FollowPlanController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new FollowPlan(), function (Grid $grid) {
            $grid->model()->orderByDesc('created_at');
            $grid->column('id')->sortable();
            $grid->column('user_id');
//            $grid->column('pair_id');
            $grid->column('pair_name');
            $grid->column('follow_name');
            $grid->column('follow_code')->copyable();
            $grid->column('side')->using(['buy' => '涨','sell' => '跌'])->label([
                'buy' => 'success',
                'sell' => 'danger',
            ]);
            $grid->column('amount');
//            $grid->column('follow_people');
            $grid->column('cycle');
            $grid->column('order_time');
            $grid->column('is_public')->using([0 => '否',1 => '是']);
            $grid->column('kongyk')->select([0 => '未设置',1 => '盈利',2 => '亏损']);
            $grid->column('profit_ratio');
            $grid->column('status')->using([ 0 => '待执行', 1 => '已执行', 2 => '已取消']);
            $grid->column('exc_remark');
            $grid->column('created_at');
//            $grid->column('updated_at')->sortable();
            $grid->disableCreateButton();
            $grid->disableRowSelector();
            $grid->disableActions();
            $grid->filter(function (Grid\Filter $filter) {
                $filter->rightSide();
//                $filter->equal('id');
                $filter->where('用户名',function($query){
                    $user_id = User::query()->where('email',$this->input)->orWhere('phone',$this->input)->value('user_id');
                    $query->where('user_id',$user_id);
                });
                $filter->equal('follow_code');
                $filter->equal('pair_name');
                $filter->equal('status')->select([0 => '待执行', 1 => '已执行', 2 => '已取消']);
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
        return Show::make($id, new FollowPlan(), function (Show $show) {
            $show->field('id');
            $show->field('user_id');
            $show->field('pair_id');
            $show->field('pair_name');
            $show->field('follow_name');
            $show->field('follow_code');
            $show->field('side');
            $show->field('amount');
            $show->field('follow_people');
            $show->field('cycle');
            $show->field('order_time');
            $show->field('is_public');
            $show->field('kongyk');
            $show->field('profit_ratio');
            $show->field('status');
            $show->field('exc_remark');
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
        return Form::make(new FollowPlan(), function (Form $form) {
            $form->display('id');
            $form->text('user_id');
            $form->text('pair_id');
            $form->text('pair_name');
            $form->text('follow_name');
            $form->text('follow_code');
            $form->text('side');
            $form->text('amount');
            $form->text('follow_people');
            $form->text('cycle');
            $form->text('order_time');
            $form->text('is_public');
            $form->text('kongyk');
            $form->text('profit_ratio');
            $form->text('status');
            $form->text('exc_remark');

            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
