<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\TimeOrder\SetLoss;
use App\Admin\Actions\TimeOrder\SetProfit;
use App\Admin\Repositories\TimeOrder;
use App\Models\FollowPlan;
use App\Models\User;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Controllers\AdminController;

class TimeOrderController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new TimeOrder(), function (Grid $grid) {
            $grid->paginate(100);
            $grid->model()->orderByDesc('created_at');
            $grid->column('id')->sortable();
//            $grid->column('follow_id');
            $grid->column('follow_code','跟单码')->display(function () {
                return FollowPlan::query()->where('id',$this->follow_id)->value('follow_code');
            });
//            $grid->column('follow_record_id');
            $grid->column('user_id');
//            $grid->column('pair_id');
            $grid->column('pair_name');
            $grid->column('side')->using(['buy' => '涨','sell' => '跌'])->label([
                'buy' => 'success',
                'sell' => 'danger',
            ]);
            $grid->column('amount');
            $grid->column('open_price');
            $grid->column('close_price');
            $grid->column('order_time');
            $grid->column('settle_time');
            $grid->column('cycle');
            $grid->column('profit_ratio');
            $grid->column('is_win')->using([0 => '否',1 => '是']);
            $grid->column('kongyk')->select([0 => '未设置',1 => '盈利',2 => '亏损']);
            $grid->column('result')->display(function () {
                if (strpos($this->result,'+') !== false){
                    return '<span style="color: green;">'.$this->result.'</span>';
                }else{
                    return '<span style="color: red;">'.$this->result.'</span>';
                }
            });
            $grid->column('status')->using([ 0 => '待下单', 1 => '进行中', 2 => '已完成']);
            $grid->column('created_at');
//            $grid->column('updated_at')->sortable();
            $grid->tools(
                [new SetProfit(),
                new SetLoss()]
            );
            // xlsx
            $titles = [
                'user_id' => 'UID',
                'username' => '用户账号',
                'follow_code' => '跟单码',
                'pair_name' => '交易对',
                'side' => '方向',
                'amount' => '下单金额',
                'open_price' => '建仓价格',
                'close_price' => '平仓价格',
                'order_time' => '下单时间',
                'settle_time' => '结算时间',
                'cycle' => '时间周期',
                'profit_ratio' => '盈亏比例',
                'is_win' => '是否盈利',
                'result' => '盈亏结果',
                'status' => '订单状态',
            ];
            $grid->export()->titles($titles)->rows(function (array $rows) use ($titles) {
                foreach ($rows as $index => &$row) {
                    $row['username'] = User::query()->where('user_id',$row['user_id'])->value('account');
                    $row['follow_code'] = FollowPlan::query()->where('id',$row['follow_id'])->value('follow_code');
                    $row['side'] = $row['side'] == 'buy' ? '涨' : '跌';
                    $row['cycle'] = $row['cycle'].'分钟';
                    $row['is_win'] = $row['is_win'] == 1 ? '是' : '否';
                    if ($row['status'] == 0) {
                        $row['status'] = '待下单';
                    }elseif ($row['status'] == 1) {
                        $row['status'] = '进行中';
                    }else{
                        $row['status'] = '已完成';
                    }
                }
                return $rows;
            })->xlsx();
            $grid->disableCreateButton();
            $grid->disableActions();
//            $grid->disableRowSelector();
            $grid->filter(function (Grid\Filter $filter) {
//                $filter->rightSide();
                $filter->where('用户名',function($query){
                    $user_id = User::query()->where('email',$this->input)->orWhere('phone',$this->input)->value('user_id');
                    $query->where('user_id',$user_id);
                })->width(2);
                $filter->where('跟单码',function($query){
                    $follow_id = FollowPlan::query()->where('folloe_code',$this->input)->value('id');
                    $query->where('follow_id',$follow_id);
                })->width(2);
                $filter->equal('pair_name')->width(2);
                $filter->equal('status')->select([0 => '待下单', 1 => '进行中', 2 => '已完成'])->width(2);
                $filter->equal('side')->select(['buy' => '买涨','sell' => '卖跌'])->width(2);
                $filter->between('order_time')->datetime(['format' => 'YYYY-MM-DD HH:mm'])->width(4);
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
        return Show::make($id, new TimeOrder(), function (Show $show) {
            $show->field('id');
            $show->field('follow_id');
            $show->field('follow_record_id');
            $show->field('user_id');
            $show->field('pair_id');
            $show->field('pair_name');
            $show->field('side');
            $show->field('amount');
            $show->field('open_price');
            $show->field('close_price');
            $show->field('order_time');
            $show->field('settle_time');
            $show->field('cycle');
            $show->field('profit_ratio');
            $show->field('is_win');
            $show->field('kongyk');
            $show->field('result');
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
        return Form::make(new TimeOrder(), function (Form $form) {
            $form->display('id');
            $form->text('follow_id');
            $form->text('follow_record_id');
            $form->text('user_id');
            $form->text('pair_id');
            $form->text('pair_name');
            $form->text('side');
            $form->text('amount');
            $form->text('open_price');
            $form->text('close_price');
            $form->text('order_time');
            $form->text('settle_time');
            $form->text('cycle');
            $form->text('profit_ratio');
            $form->text('is_win');
            $form->text('kongyk');
            $form->text('result');
            $form->text('status');

            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
