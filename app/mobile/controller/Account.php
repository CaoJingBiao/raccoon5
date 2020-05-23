<?php


namespace app\mobile\controller;

use app\common\RedisHelper;
use app\model\User;
use app\model\UserFinance;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\facade\View;

class Account extends Base
{
    public function register()
    {
        if (request()->isPost()) {
            $captcha = input('captcha');
            if( !captcha_check($captcha ))
            {
                return ['err' => 1, 'msg' => '验证码错误'];
            }
            $ip = request()->ip();
            $redis = RedisHelper::GetInstance();
            if ($redis->exists('user_reg:' . $ip)) {
                return ['err' => 1, 'msg' => '操作太频繁'];
            } else {
                $data = request()->param();
                $validate = new \app\validate\User();
                if ($validate->check($data)) {
                    try {
                        $user = User::where('username', '=', trim(request()->param('username')))->findOrFail();
                        return ['err' => 1, 'msg' => '用户名已经存在'];
                    } catch (DataNotFoundException $e) {
                    } catch (ModelNotFoundException $e) {
                        $user = new User();
                        $user->username = trim($data['username']);
                        $user->password = trim($data['password']);
                        $pid = cookie('xwx_promotion');
                        if (!$pid) {
                            $pid = 0;
                        }
                        $user->pid = $pid; //设置用户上线id
                        $user->reg_ip = request()->ip();
                        $result = $user->save();
                        if ($result) {
                            $redis->set('user_reg:'.$ip,1,60); //写入锁
                            if ($pid > 0) {
                                try {
                                    $puser = User::findOrFail($pid);
                                    if ($puser) {
                                        $finance = new UserFinance();
                                        $finance->user_id = $pid;
                                        $finance->money = config('payment.reg_rewards');
                                        $finance->usage = 4;
                                        $finance->summary = '下线注册奖励';
                                        $finance->save();
                                    }
                                } catch (DataNotFoundException $e) {
                                } catch (ModelNotFoundException $e) {
                                }
                            }
                            return ['err' => 0, 'msg' => '注册成功，请登录'];
                        } else {
                            return ['err' => 1, 'msg' => '注册失败，请尝试重新注册'];
                        }
                    }
                } else {
                    return ['err' => 1, 'msg' => $validate->getError()];
                }
            }
        } else {
            View::assign([
                'site_name' => config('site.site_name'),
                'url' => config('site.schema').config('site.url'),
                'book_ctrl' => BOOKCTRL,
                'chapter_ctrl' => CHAPTERCTRL,
                'booklist_act' => BOOKLISTACT,
                'search_ctrl' => SEARCHCTRL,
                'rank_ctrl' => RANKCTRL,
                'update_act' => UPDATEACT,
                'author_ctrl' => AUTHORCTRL,
                'cdn' => config('site.cdn'),
                'header_title' => '用户注册'
            ]);
            return view($this->tpl);
        }
    }

    public function login()
    {
        if (request()->isPost()) {
            $captcha = input('captcha');
            if( !captcha_check($captcha ))
            {
                return ['err' => 1, 'msg' => '验证码错误'];
            }
            $map = array();
            $map[] = ['username', '=', trim(input('username'))];
            $map[] = ['password', '=', md5(strtolower(trim(input('password'))) . config('site.salt'))];
            try {
                $user = User::withTrashed()->where($map)->findOrFail();
                if ($user->delete_time > 0) {
                    return ['err' => 1, 'msg' => '用户被锁定'];
                } else {
                    $user->last_login_time = time();
                    $user->save();
                    session('xwx_user_id', $user->id);
                    session('xwx_user', $user->username);
                    session('xwx_nick_name', $user->nick_name);
                    session('xwx_user_mobile', $user->mobile);
                    session('xwx_vip_expire_time', $user->vip_expire_time);
                    return ['err' => 0, 'msg' => '登录成功'];
                }
            } catch (DataNotFoundException $e) {
            } catch (ModelNotFoundException $e) {
                return ['err' => 1, 'msg' => '用户名或密码错误'];
            }
        } else {
            View::assign([
                'site_name' => config('site.site_name'),
                'url' => config('site.url'),
                'book_ctrl' => BOOKCTRL,
                'chapter_ctrl' => CHAPTERCTRL,
                'booklist_act' => BOOKLISTACT,
                'search_ctrl' => SEARCHCTRL,
                'rank_ctrl' => RANKCTRL,
                'update_act' => UPDATEACT,
                'author_ctrl' => AUTHORCTRL,
                'cdn' => config('site.cdn'),
                'header_title' => '用户登录'
            ]);
            return view($this->tpl);
        }
    }

    public function logout()
    {
        session('xwx_user', null);
        session('xwx_user_id', null);
        session('xwx_nick_name', null);
        session('xwx_user_mobile',null);
        session('xwx_vip_expire_time', null);
        $this->redirect('/login');
    }


    public function captcha()
    {
        ob_clean();
        return captcha();
    }
}