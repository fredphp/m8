<?php

namespace App\Admin\Controllers;

use App\Admin\Repositories\FollowRecord;
use App\Models\FollowPlan;
use App\Models\User;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Controllers\AdminController;

class FollowRecordController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new FollowRecord(), function (Grid $grid) {
            $grid->model()->orderByDesc('created_at');
            $grid->column('id')->sortable();
            $grid->column('follow_id');
            $grid->column('follow_code','跟单码')->display(function () {
                return FollowPlan::query()->where('id',$this->follow_id)->value('follow_code');
            });
            $grid->column('user_id');
//            $grid->column('pair_id');
            $grid->column('pair_name');
            $grid->column('side')->using(['buy' => '涨','sell' => '跌'])->label([
                'buy' => 'success',
                'sell' => 'danger',
            ]);;
            $grid->column('amount');
            $grid->column('cycle');
            $grid->column('order_time');
            $grid->column('profit_ratio');
            $grid->column('kongyk')->select([0 => '未设置',1 => '盈利',2 => '亏损']);;
            $grid->column('status')->using([ 0 => '待执行', 1 => '跟单中', 2 => '已完成', 3 => '已取消']);
            $grid->column('created_at');
            $grid->disableActions();
            $grid->disableRowSelector();
            $grid->disableCreateButton();
//            $grid->column('updated_at')->sortable();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->rightSide();
                $filter->where('用户名',function($query){
                    $user_id = User::query()->where('email',$this->input)->orWhere('phone',$this->input)->value('user_id');
                    $query->where('user_id',$user_id);
                });
                $filter->where('跟单码',function($query){
                    $follow_id = FollowPlan::query()->where('folloe_code',$this->input)->value('id');
                    $query->where('follow_id',$follow_id);
                });
                $filter->equal('pair_name');
                $filter->equal('status')->select([ 0 => '待执行', 1 => '跟单中', 2 => '已完成', 3 => '已取消']);
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
        return Show::make($id, new FollowRecord(), function (Show $show) {
            $show->field('id');
            $show->field('follow_id');
            $show->field('user_id');
            $show->field('pair_id');
            $show->field('pair_name');
            $show->field('side');
            $show->field('amount');
            $show->field('cycle');
            $show->field('order_time');
            $show->field('profit_ratio');
            $show->field('kongyk');
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
        return Form::make(new FollowRecord(), function (Form $form) {
            $form->display('id');
            $form->text('follow_id');
            $form->text('user_id');
            $form->text('pair_id');
            $form->text('pair_name');
            $form->text('side');
            $form->text('amount');
            $form->text('cycle');
            $form->text('order_time');
            $form->text('profit_ratio');
            $form->text('kongyk');
            $form->text('status');

            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
