<?php

namespace App\Admin\Controllers;


use App\Admin\Actions\Pertain;
use App\Admin\Actions\User\AddSystemUser;
use App\Admin\Actions\User\DisableMoney;
use App\Admin\Actions\User\MarkSystemUser;
use App\Admin\Actions\User\ModifyExc;
use App\Admin\Actions\User\ModifyPayPassword;
use App\Admin\Actions\User\recharge;
use App\Admin\Renderable\UserChildExpand;
use App\Admin\Renderable\UserTradeStatistics;
use App\Admin\Renderable\UserWalletExpand;
use App\Models\Agent;
use App\Models\AgentGrade;
use App\Models\Country;
use App\Models\SustainableAccount;
use App\Models\TimeOrder;
use App\Models\User;
use App\Models\UserGrade;
use App\Models\UserWallet;
use App\Models\VolLog;
use Dcat\Admin\Actions\Response;
use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Controllers\AdminController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use function foo\func;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Admin\Actions\User\ModifyPassword;
use App\Admin\Actions\User\RestorePassword;
use Dcat\Admin\Widgets\Alert;

class UserController extends AdminController
{
    protected $title = '用户列表';

    protected function grid()
    {
        return Grid::make(User::query()->where('is_agency', 0), function (Grid $grid) {
            $grid->model()->orderByDesc('id');
            if (Admin::user()->inRoles(['员工', '经理级'])) {
                $preUser = User::where('username', Admin::user()->username)->first();
                $lowerIds = getLowerIds($preUser->user_id);
                $grid->model()->whereIn('user_id', $lowerIds);
            };
            $grid->header(function ($collection) use ($grid) {
                // 获取筛选参数
                $filterParams = request()->all();
                $start_time = $filterParams['created_at']['start'] ?? date('Y-m-d 00:00:00',time());
                $end_time = $filterParams['created_at']['end'] ?? date('Y-m-d 23:59:59',time());

                $builder = User::query();
                $totalUserCount = $builder->count();
//                $todayReg = $builder->whereBetween('created_at', [$start_time,$end_time])->count();
                $isOnline = User::query()->where('is_online', 1)->count();
                $totalRecharge = \App\Models\Recharge::query()->where('type',1);
                if (array_key_exists('created_at',$filterParams)){
                    if ($filterParams['created_at']['start']){
                        $totalRecharge = $totalRecharge->where('created_at','>=',$filterParams['created_at']['start']);
                    }
                    if ($filterParams['created_at']['end']){
                        $totalRecharge = $totalRecharge->where('created_at','<=',$filterParams['created_at']['end']);
                    }
                }

                $totalRecharge = $totalRecharge->where('status',1)->sum('amount');
                $todayReg = \App\Models\Recharge::query()->where(['type' => 1,'status' => 1])->where('created_at','>=',date('Y-m-d 00:00:00'))->sum('amount');

                $totalWithdraw = \App\Models\Withdraw::query();
                if (array_key_exists('created_at',$filterParams)){
                    if ($filterParams['created_at']['start']) {
                        $totalWithdraw = $totalWithdraw->where('created_at', '>=', $filterParams['created_at']['start']);
                    }
                    if ($filterParams['created_at']['end']) {
                        $totalWithdraw = $totalWithdraw->where('created_at', '<=', $filterParams['created_at']['end']);
                    }
                }
                $totalWithdraw = $totalWithdraw->where('status',1)->sum('amount');
                $todayWithdraw = \App\Models\Withdraw::query()->where(['status' => 1])->where('created_at','>=',date('Y-m-d 00:00:00'))->sum('amount');
                $con = '';
                $con .= '<code>'.'用户人数：'.$totalUserCount.'</code> ';
                $con .= '<code>'.'今日注册：'.$todayReg.'</code> ';
                $con .= '<code>'.'在线人数：'.$isOnline.'</code> ';
                $con .= '<code>'.'总充值：'.$totalRecharge.'</code> ';
                $con .= '<code>'.'总提现：'.$totalWithdraw.'</code> ';
                $con .= '<code>'.'总留存：'.($totalRecharge - $totalWithdraw).'</code> ';
                $con .= '<code>'.'今日充值：'.$todayReg.'</code> ';
                $con .= '<code>'.'今日提现：'.$todayWithdraw.'</code> ';
                $con .= '<code>'.'今日留存：'.($todayReg - $todayWithdraw).'</code> ';
                return Alert::make($con, '统计')->info();
            });
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                $actions->disableDelete();
                //$actions->disableQuickEdit();
                //$actions->disableEdit();
                $actions->disableView();

                if (Admin::user()->isRole('超级管理员')) {
                    $actions->append(new recharge());
                    $actions->append(new DisableMoney());
//                    $actions->append(new Pertain());
                    $actions->append(new ModifyPassword());
                    $actions->append(new ModifyPayPassword());
                    $actions->append(new ModifyExc());
//                    $actions->append(new RestorePassword());
                }
//                if (Admin::user()->can('addSystemUser')) {
//                    $actions->append(new MarkSystemUser());
//                }
            });

            if (Admin::user()->isRole('超级管理员')) {
                $grid->tools([new AddSystemUser()]);
            }

            // xlsx
            $titles = ['user_id' => 'UID', 'pid' => 'PID', 'phone' => '电话', 'email' => '邮箱', 'invite_code' => '邀请码', 'user_grade' => '级别', 'user_auth_level' => '认证状态', 'status' => '状态', 'created_at' => '时间'];
            $grid->export()->titles($titles)->rows(function (array $rows) use ($titles) {
                foreach ($rows as $index => &$row) {
                    $row['user_grade'] = UserGrade::get_grade_info($row['user_grade'])['grade_name'] ?? $row['user_grade'];
                    $row['user_auth_level'] = User::$userAuthMap[$row['user_auth_level']];
                    $row['status'] = User::$userStatusMap[$row['status']];
                }
                return $rows;
            })->xlsx();

            $grades = AgentGrade::getCachedGradeOption();
            $grid->fixColumns(1);
//            $grid->model()->orderByDesc('created_at');
            $grid->column('下级')->display('展开')->expand(UserChildExpand::make());
            $grid->user_id;
//            $grid->account;
//            $grid->username;
            $grid->pid;

            $grid->phone;
            $grid->email;
//            $grid->avatar->image('', 50, 50);
            $grid->invite_code;
            $grid->column('is_online','在线状态')->using([0 => '离线',1=>'在线'])->dot([
                0 => 'danger',
                1 => 'success',
            ]);
            $grid->column('import', '入金')->display(function () {
                $a = countUserDis($this->user_id);
                return $a['i'];
            });
            $grid->column('export', '出金')->display(function () {
                $a = countUserDis($this->user_id);
                return $a['e'];
            });
            $grid->column('diff', '净入金')->display(function () {
                $a = countUserDis($this->user_id);
                return $a['d'];
            });
            $grid->column('exc_num','充值量/交易量')->display(function () {
                  $recharge = \App\Models\Recharge::where(['user_id' => $this->user_id, 'status' => 1,'coin_id' => 1])->sum('amount') ?? 0;
                  $exchangeAmount = TimeOrder::query()->where(['user_id' => $this->user_id, 'status' => 2])->sum('amount') ?? 0;
                  $exchangeAmount += VolLog::query()->where(['user_id' => $this->user_id])->sum('number') ?? 0;
                  return $recharge . '/' .$exchangeAmount;
            });
//            $grid->purchase_code;
//            $grid->user_grade->display(function ($v) {
//                return UserGrade::get_grade_info($v)['grade_name'] ?? $v;
//            })->label('info');
//            $grid->user_auth_level->using(User::$userAuthMap)->dot([0 => 'danger', 1 => 'info', 2 => 'success']);

//            $grid->column('统计')->display('统计')->expand(UserTradeStatistics::make());
            $grid->column('币币资产')->display('资产')->expand(UserWalletExpand::make());
            $grid->column('总折合')->display(function () {
                $total = 0;
                $data = UserWallet::query()->where('user_id',$this->user_id)->get(['user_id', 'coin_name', 'usable_balance', 'freeze_balance'])->toArray();
                foreach ($data as $item) {
                    $coin_name = $item['coin_name'];
                    if ($coin_name == "USDT") {
                        $price = 1;
                    } else {
                        $currency = strtolower($coin_name . "usdt");
                        $cachePrice = Cache::store('redis')->get('market:' . "$currency" . '_detail');
                        $price = $cachePrice ? $cachePrice['close'] : 0;
                    }
                    $total += floatval(custom_number_format(($item['usable_balance'] + $item['freeze_balance']) * $price, 5));
                }
                return $total;
            });
//            $grid->column('合约资产')->display(function () {
//                $account = SustainableAccount::where(['user_id' => $this->user_id, 'coin_name' => 'USDT'])->first();
//                if (!empty($account)) {
//                    return $account->usable_balance + $account->used_balance ;
//                } else {
//                    return 0;
//                }
//            });

            $grid->status->switch();
            $grid->trade_status->switch();
//            $grid->column('is_system','系统账户')->switch()->filter(Grid\Column\Filter\In::make([0=>'否',1=>'是']));
//            $grid->column('is_system', '系统账户')->using([0 => '否', 1 => '是'])->badge([0 => 'danger', 1 => 'success'])->filter(Grid\Column\Filter\In::make([0 => '否', 1 => '是']));

//            $grid->last_login_time;
//            $grid->last_login_ip;
            $grid->column('security_issuse','问题')->using([
                1 => '你自己的生日？',
                2 => '你第一次旅行去的城市？',
                3 => '你第一辆车的品牌？',
                4 => '你最喜欢的电影名字？',
                5 => '你第一份工作的所在城市？',
                6 => '你第一只宠物的名字？',
                7 => '你最喜欢的颜色？',
                8 => '你最喜欢的运动队名称？'
            ]);
            $grid->column('security_answer','答案');
            $grid->created_at->sortable();

//            $grid->disableViewButton();
            $grid->disableCreateButton();
            if (!Admin::user()->inRoles(['超级管理员'])) {
                $grid->disableEditButton();
            };
            $grid->disableDeleteButton();
            $grid->disableBatchDelete();

            $grid->filter(function (Grid\Filter $filter) use ($grades) {
                $filter->equal('user_id', 'UID')->width(3);
                $filter->where('username', function ($q) {
                    $q->where('username', $this->input)->orWhere('phone', $this->input)->orWhere('email', $this->input);
                }, "用户名/手机/邮箱")->width(3);
                $filter->between('created_at', "时间")->datetime()->width(4);
                $filter->where('sort',function ($q){
                    $sortArr = [];
                    $userIds = User::query()->get(['user_id']);
                    foreach ($userIds as &$userId) {
                        $total = 0;
                        $data = UserWallet::query()->where('user_id',$userId->user_id)->get(['user_id', 'coin_name', 'usable_balance', 'freeze_balance'])->toArray();
                        foreach ($data as $item) {
                            $coin_name = $item['coin_name'];
                            if ($coin_name == "USDT") {
                                $price = 1;
                            } else {
                                $currency = strtolower($coin_name . "usdt");
                                $cachePrice = Cache::store('redis')->get('market:' . "$currency" . '_detail');
                                $price = $cachePrice ? $cachePrice['close'] : 0;
                            }
                            $total += floatval(custom_number_format(($item['usable_balance'] + $item['freeze_balance']) * $price, 5));
                        }
                        Log::info("用户ID:".$userId->user_id.'总资产：'.$total);
                        $userId->total = $total;
                        array_push($sortArr,[
                            'user_id' => $userId->user_id,
                            'total' => $total,
                        ]);
                    }
                    if ($this->input == 0){
                        $sorted = collect($sortArr)->sortBy('total')->values()->all();
                    }else{
                        $sorted = collect($sortArr)->sortByDesc('total')->values()->all();
                    }
                    $ids = array_column($sorted, 'user_id');
                    $q->whereIn('user_id', $ids)->orderByRaw('FIELD(user_id, ' . implode(',', $ids) . ')');
                },'总资产排序')->select([0 => '正序',1 =>'倒序'])->width(2);
            });
        });
    }

    public function agents(Request $request)
    {
        $q = $request->get('q');
        $options = Agent::query()->where(['pid' => $q, 'is_agency' => 1])->select(['id', 'username as text'])->get()->toArray();
        array_unshift($options, []);
        return $options;
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
        return Show::make($id, new User(), function (Show $show) {
            $show->user_id;
            $show->account;
            $show->account_type;
            $show->username;
            $show->pid;
            $show->deep;
            $show->path;
            $show->country_code;
            $show->phone;
            $show->email;
            $show->avatar;
            $show->password;
            $show->payword;
            $show->invite_code;
            $show->user_grade;
            $show->user_identity;
            $show->user_auth_level;
            $show->login_code;
            $show->status;
            $show->reg_ip;
            $show->last_login_time;
            $show->last_login_ip;
            $show->created_at;
            $show->updated_at;
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        if (!Admin::user()->inRoles(['超级管理员'])) {
            throw new \Exception('权限不足');
        };
        return Form::make(new User(), function (Form $form) {

            $form->text('user_id')->readOnly();
            $form->text('username')->rules("required:users,username")->readOnly();
            $form->text('name');
            $form->switch('status');
            $form->switch('trade_status');
            $form->switch('is_system');
            $form->text('pid', "上级ID")->rules("required:users,pid");
            $form->text('referrer', "代理ID");
            $form->text('invite_code')->display(false);
            // $form->select("is_agency","是否代理")->options([0=>"用户",1=>'代理']);

        });
    }
}
