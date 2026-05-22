<?php

namespace App\Admin\Forms\User;

use App\Models\SustainableAccount;
use App\Models\UserWallet;
use App\UserDisMoney;
use Dcat\Admin\Widgets\Form;
use Symfony\Component\HttpFoundation\Response;

class DisableMoney extends Form
{

    // 增加一个自定义属性保存ID
    protected $user_id;

    // 构造方法的参数必须设置默认值
    public function __construct($user_id = null)
    {
        $this->user_id = $user_id;

        parent::__construct();
    }

    /**
     * Handle the form request.
     *
     * @param array $input
     *
     * @return Response
     */
    public function handle(array $input)
    {

        if ($input['account_type'] == 1) {
            $account = UserWallet::where(['user_id' => $input['user_id'], 'coin_name' => $input['coin_name']])->first();
            if (!$account) return $this->error('该用户还没创建账户');
        } else { //合约账户
            if ($input['coin_name'] == 'BTC' || $input['coin_name'] == 'ETH') {
                return $this->error('合约账户只有USDT资金');
            }
            $account = SustainableAccount::where(['user_id' => $input['user_id'], 'coin_name' => $input['coin_name']])->first();
            if (!$account) return $this->error('该用户还没创建账户');
        }

        if ($input['op_type'] == 1) { //冻结
            if ($account->usable_balance < $input['money']) return $this->error('资金不足');
            $account->usable_balance -= $input['money'];
            $account->freeze_balance += $input['money'];
        } else {
            if ($account->freeze_balance < $input['money']) return $this->error('解冻资金不足');
            $account->usable_balance += $input['money'];
            $account->freeze_balance -= $input['money'];
        }
        $log = new UserDisMoney();
        $log->user_id = $input['user_id'];
        $log->coin_name = $input['coin_name'];
        $log->account_type = $input['account_type'];
        $log->op_type = $input['op_type'];
        $log->money = $input['money'];
        $account->save();
        $log->save();
        // return $this->error('Your error message.');

        return $this->success('操作成功');
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        $this->select('coin_name', '币种')->required()->options(['USDT' => 'USDT', 'BTC' => 'BTC', 'ETH' => 'ETH']);
        $this->select('account_type', '账户类型')->required()->options([1 => '币币账户', 2 => '合约账户']);
        $this->radio('op_type', '操作类型')->required()->options([1 => '冻结', 2 => '解冻']);
        $this->text('money', '金额')->required()->placeholder('请输入大于0的金额');
        // 设置隐藏表单，传递用户id
        $this->hidden('user_id')->value($this->user_id);
    }

    /**
     * The data of the form.
     *
     * @return array
     */
    public function default()
    {
        return [
//            'name'  => 'John Doe',
//            'email' => 'John.Doe@gmail.com',
        ];
    }
}
