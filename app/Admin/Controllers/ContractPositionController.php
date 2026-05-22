<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\ContractPosition\Flat;
use App\Admin\Actions\ContractPosition\OnekeyFlatPosition;
use App\Handlers\ContractTool;
use App\Models\Agent;
use App\Models\AgentGrade;
use App\Models\ContractPosition;
use App\Models\ContractStrategy;
use App\Models\ContractTmp;
use App\Models\SustainableAccount;
use App\Models\User;
use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Controllers\AdminController;
use function foo\func;
use Illuminate\Support\Facades\Cache;

class ContractPositionController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $builder = ContractPosition::query();
        if (Admin::user()->inRoles(['员工', '经理级'])) {
            $preUser = User::where('username', Admin::user()->username)->first();
            $lowerIds = getLowerIds($preUser->user_id);
            $builder->whereIn('user_id', $lowerIds);
        };
        return Grid::make($builder, function (Grid $grid) {
            $grid->model()->orderByDesc('id');

            $grid->disableRowSelector();
            $grid->disableCreateButton();
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                $actions->disableDelete();
                $actions->disableQuickEdit();
                $actions->disableEdit();
                $actions->disableView();
                if (Admin::user()->inRoles(['超级管理员'])) {
                    $actions->append(new Flat());
                }
            });
            if (Admin::user()->inRoles(['超级管理员'])) {
                $grid->tools([new OnekeyFlatPosition()]);
            }
            $grid->column('id')->sortable();
            $grid->column('user_id', '用户ID');
            $grid->column('symbol', '标的');
            $grid->column('side')->using([1 => '多', 2 => '空'])->label([1 => 'info', 2 => 'danger']);
//            $grid->column('contract_id');
//            $grid->column('unit_amount');
            $grid->column('lever_rate', '杠杆');
            $grid->column('hold_position', '持有张数');
            $grid->column('avail_position', '可平张数')->editable();
            $grid->column('freeze_position', '冻结张数');
            $grid->column('position_margin', '保证金');
            $grid->column('total_scale', '下单占比')->display(function () {
                $tmp = ContractTmp::where(['user_id' => $this->user_id, 'symbol' => $this->symbol, 'lever_rate' => $this->lever_rate, 'side' => $this->side, 'status' => 1])->where('created_at', '<', $this->created_at)->orderBy('created_at', 'desc')->first();
                return $tmp ? $tmp->scale . '%' : '未找到';
            });
            $grid->column('avg_price', '均价');
            $grid->column('stop_up', '止盈价')->display(function () {
                $strategy = ContractStrategy::query()
                    ->where('position_id', $this->id)
                    ->first();
                return $strategy['tp_trigger_price'] ?? 0;
            });
            $grid->column('stop_less', '止损价')->display(function () {
                $strategy = ContractStrategy::query()
                    ->where('position_id', $this->id)
                    ->first();
                return $strategy['sl_trigger_price'] ?? 0;
            });

            $grid->column('unRealProfit', '盈利百分比')->display(function () {
                if ($this->status == 1){
                    $realtime_price = Cache::store('redis')->get('swap:' . 'trade_detail_' . $this->symbol)['price'] ?? null;
                    $profit = ContractTool::unRealProfit($this, ['unit_amount' => $this->unit_amount], $realtime_price);
                    return strval(round($profit / $this->position_margin, 4) * 100) . '%';
                }else{
                    return '--';
                }
            });
            $grid->column('status', '状态')->using([1 => '持仓中', 2 => '已平仓'])->dot([1 => 'danger',2 => 'success']);
//            $grid->column('updated_at')->sortable();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('user_id')->width(3);
                $filter->where('username', function ($q) {
                    $username = $this->input;
                    $q->whereHas('user', function ($q) use ($username) {
                        $q->where('username', $username)->orWhere('phone', $username)->orWhere('email', $username);
                    });
                }, "用户名/手机/邮箱")->width(3);
                $filter->equal('symbol')->width(3);
                $filter->equal('status')->width(3)->select([1 => '持仓中', 2 => '已平仓']);
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
        return Show::make($id, new ContractPosition(), function (Show $show) {
            $show->field('id');
            $show->field('user_id');
            $show->field('side');
            $show->field('contract_id');
            $show->field('symbol');
            $show->field('unit_amount');
            $show->field('lever_rate');
            $show->field('hold_position');
            $show->field('avail_position');
            $show->field('freeze_position');
            $show->field('position_margin');
            $show->field('avg_price');
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
        return Form::make(new ContractPosition(), function (Form $form) {
            $form->display('id');
            $form->text('user_id');
            $form->text('side');
            $form->text('contract_id');
            $form->text('symbol');
            $form->text('unit_amount');
            $form->text('lever_rate');
            $form->text('hold_position');
            $form->text('avail_position');
            $form->text('freeze_position');
            $form->text('position_margin');
            $form->text('avg_price');

            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
