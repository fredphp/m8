<?php

namespace App\Admin\Controllers;

use App\Models\RewardConfig;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Controllers\AdminController;
// 小白
class RewardConfigController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new RewardConfig(), function (Grid $grid) {

            $grid->disableDeleteButton();
            $grid->disableRowSelector();
            $grid->disableCreateButton();

            $grid->column('id')->sortable();
            // $grid->column('client_type')->using([1=>'Android',2=>'IOS'])->badge([1=>'info',2=>'success']);
            $grid->column('register_reward','注册奖励');
            $grid->column('register_sup_reward','注册奖励上级');
            $grid->column('register_team_reward','注册奖励团队');
            $grid->column('buy_sup_reward','上级分红比例');
            $grid->column('buy_sup_team_reward','上上级分红比例');
            $grid->column('sign_reward','签到奖励');
            $grid->column('lx_sign_reward','连续签到奖励');
            $grid->column('updated_at');

            $grid->filter(function (Grid\Filter $filter) {

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
        return Show::make($id, new RewardConfig(), function (Show $show) {
            $show->field('id');
            $show->field('register_reward','注册奖励');
            $show->field('register_sup_reward','注册奖励上级');
            $show->field('register_team_reward','注册奖励团队');
            $show->field('buy_sup_reward','上级分红比例');
            $show->field('buy_sup_team_reward','上上级分红比例');
            $show->field('sign_reward','签到奖励');
            $show->field('lx_sign_reward','连续签到奖励');
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
        return Form::make(new RewardConfig(), function (Form $form) {
            $form->display('id');
            $form->text('register_reward','注册奖励');
            $form->text('register_sup_reward','注册奖励上级');
            $form->text('register_team_reward','注册奖励团队');
            $form->text('buy_sup_reward','上级分红比例');
            $form->text('buy_sup_team_reward','上上级分红比例');
            $form->text('sign_reward','签到奖励');
            $form->text('lx_sign_reward','连续签到奖励');
        });
    }
}
