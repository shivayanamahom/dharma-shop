<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Promotion;

use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Общая работа с применённой акцией, расчётом корзины и атомарной фиксацией заказа. */
final class PromotionService
{
    public function __construct(private readonly DatabaseInterface $db) {}

    public static function normalizeCode(string $code): string
    {
        $code = mb_strtoupper(trim($code), 'UTF-8');
        return preg_match('/^[A-ZА-ЯЁ0-9][A-ZА-ЯЁ0-9_-]{0,63}$/uD', $code) ? $code : '';
    }

    /** Сохраняет ровно один код на корзину, только если акция сейчас доступна. */
    public function applyCode(object $cart, string $rawCode, ?string $email = null): void
    {
        $code = self::normalizeCode($rawCode);
        if ($code === '') throw new \DomainException(Text::_('COM_DHARMASHOP_PROMOTION_CODE_INVALID'));
        $query = $this->db->createQuery()->select(['p.id','pc.id AS promotion_code_id','pc.code','pc.params','p.plugin','p.state','p.access','p.starts','p.ends'])
            ->from($this->db->quoteName('#__dharmashop_promotion_codes','pc'))
            ->join('INNER',$this->db->quoteName('#__dharmashop_promotions','p'),'p.id=pc.promotion_id')
            ->where('pc.code = :code')->where('p.state = 1')->bind(':code', $code);
        $row = $this->db->setQuery($query, 0, 1)->loadObject();
        if (!$row) throw new \DomainException(Text::_('COM_DHARMASHOP_PROMOTION_CODE_INVALID'));
        if (TypeRegistry::current()->find((string) $row->plugin) === null) throw new \DomainException(Text::_('COM_DHARMASHOP_PROMOTION_UNAVAILABLE'));
        $settings=(new Registry((string)$row->params))->toArray();
        if(!$this->isAvailable($row,$settings))throw new \DomainException(Text::_('COM_DHARMASHOP_PROMOTION_CODE_INVALID'));
        $buyer = $this->buyer($cart, $email);
        $error = $this->usageError('promotion_code_id', (int) $row->promotion_code_id, $settings, $buyer);
        if ($error !== '') throw new \DomainException(Text::_($error));
        Sql::exec($this->db, 'INSERT INTO #__dharmashop_cart_promotions (cart_id,promotion_id,promotion_code_id,code,applied) VALUES (:cart,:promotion,:code_id,:code,:applied) ON DUPLICATE KEY UPDATE promotion_id=VALUES(promotion_id),promotion_code_id=VALUES(promotion_code_id),code=VALUES(code),applied=VALUES(applied)',
            [':cart'=>[(int)$cart->id,ParameterType::INTEGER],':promotion'=>[(int)$row->id,ParameterType::INTEGER],':code_id'=>[(int)$row->promotion_code_id,ParameterType::INTEGER],':code'=>[$code],':applied'=>[Factory::getDate()->toSql()]]);
    }

    public function removeCode(object $cart): void
    {
        Sql::exec($this->db, 'DELETE FROM #__dharmashop_cart_promotions WHERE cart_id=:cart', [':cart'=>[(int)$cart->id, ParameterType::INTEGER]]);
    }

    /** Есть ли опубликованный код, доступный этому посетителю в текущий период. */
    public function hasAvailableCode(): bool
    {
        $registry = TypeRegistry::current();
        $query = $this->db->createQuery()->select(['p.plugin', 'p.state', 'p.access', 'p.starts', 'p.ends', 'pc.params'])
            ->from($this->db->quoteName('#__dharmashop_promotion_codes', 'pc'))
            ->join('INNER', $this->db->quoteName('#__dharmashop_promotions', 'p'), 'p.id=pc.promotion_id')
            ->where('p.state=1')->where('pc.code IS NOT NULL');

        foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
            $type = $registry->find((string) $row->plugin);
            if (!$type || !$type->requiresCode()) {
                continue;
            }

            $settings = (new Registry((string) $row->params))->toArray();
            if ($this->isAvailable($row, $settings)) {
                return true;
            }
        }

        return false;
    }

    /** @param object[] $lines @return array{adjustments:array,promotion_uses:array} */
    public function calculate(object $cart, array $lines, float $subtotal, array $skipPromotionIds = [], ?string $email = null): array
    {
        $registry = TypeRegistry::current();
        $query = $this->db->createQuery()->select(['cp.code AS applied_code','cp.promotion_code_id','p.id','p.title','p.plugin','pc.code','pc.params','p.state','p.access','p.starts','p.ends'])
            ->from($this->db->quoteName('#__dharmashop_cart_promotions','cp'))
            ->join('INNER', $this->db->quoteName('#__dharmashop_promotions','p'), 'p.id=cp.promotion_id')
            ->join('LEFT', $this->db->quoteName('#__dharmashop_promotion_codes','pc'), 'pc.id=cp.promotion_code_id')
            ->where('cp.cart_id=:cart');
        $cartId = (int) $cart->id;
        $query->bind(':cart', $cartId, ParameterType::INTEGER);
        $rows = [];
        $manual = $this->db->setQuery($query,0,1)->loadObject();
        if ($manual) $rows[(int)$manual->id] = $manual;
        $autoQuery = $this->db->createQuery()->select(['id','title','plugin','code','state','access','starts','ends','params'])
            ->from($this->db->quoteName('#__dharmashop_promotions'))->where('code IS NULL')->where('state=1')->order('ordering,id');
        foreach ($this->db->setQuery($autoQuery)->loadObjectList() as $candidate) {
            $type = $registry->find((string)$candidate->plugin);
            if ($type && !$type->requiresCode()) { $candidate->applied_code=''; $rows[(int)$candidate->id]=$candidate; }
        }
        $adjustments = [];
        $uses = [];
        $remainingDiscount = max(0.0,$subtotal);
        $appliedCode = '';
        $description = '';
        $promotionError = '';
        $pendingIdentity = false;
        $inactive = false;
        $buyer = $this->buyer($cart, $email);
        $skip = array_fill_keys(array_map('intval',$skipPromotionIds),true);
        foreach ($rows as $row) {
            if (isset($skip[(int)$row->id])) continue;
            $row->params=(new Registry((string)($row->params??'')))->toArray();
            if (!$this->isAvailable($row,!empty($row->promotion_code_id)?$row->params:null)) { if ((string)$row->applied_code!=='') { $appliedCode=(string)$row->applied_code; $promotionError='COM_DHARMASHOP_PROMOTION_CODE_INVALID'; } continue; }
            $type=$registry->find((string)$row->plugin);if(!$type)continue;
            if ((string)$row->applied_code!=='') $appliedCode=(string)$row->applied_code;
            $isManual = (string) $row->applied_code !== '';
            $usageColumn = !empty($row->promotion_code_id) ? 'promotion_code_id' : 'promotion_id';
            $usageId = (int) ($row->promotion_code_id ?? $row->id);
            $usageError = $this->usageError($usageColumn, $usageId, $row->params, $buyer);
            if ($usageError !== '') {
                if ($isManual) $promotionError = $usageError;
                continue;
            }
            if ($isManual && (int) ($row->params['customer_limit'] ?? 0) > 0 && !$buyer['hashes'] && !$buyer['customer_id']) {
                $pendingIdentity = true;
            }
            $result=$type->apply($row,new Context($cart,$lines,$subtotal));
            if (!$result) { if ((string)$row->applied_code!=='') { $description=(string)($row->params['description']??''); $inactive = true; } continue; }
            if ((string)$row->applied_code!=='' && isset($result->snapshot['description'])) $description=(string)$result->snapshot['description'];
            foreach ($result->adjustments as $adjustment) {
                $amount=(float)($adjustment['amount']??0);
                if(!is_finite($amount)||$amount>=0||$remainingDiscount<=0)continue;
                $amount=-min(abs($amount),$remainingDiscount);$remainingDiscount=round($remainingDiscount-abs($amount),2);
                if($amount===0.0)continue;
                $adjustment['amount']=round($amount,2);$adjustments[]=$adjustment;
                $id=(int)$row->id;$uses[$id]??=['promotion_id'=>$id,'promotion_code_id'=>(int)($row->promotion_code_id??0) ?: null,'code'=>$row->code!==null?(string)$row->code:null,'amount'=>0.0];
                $uses[$id]['amount']=round($uses[$id]['amount']+abs($amount),2);
            }
        }
        return ['adjustments'=>$adjustments,'promotion_uses'=>array_values($uses),'applied_code'=>$appliedCode,'applied_description'=>$description,'promotion_error'=>$promotionError,'promotion_pending_identity'=>$pendingIdentity,'promotion_inactive'=>$inactive];
    }

    /** Блокирует акции и повторно проверяет лимиты внутри транзакции заказа. */
    public function lockForOrder(object $cart, string $email, int $userId = 0): array
    {
        $registry=TypeRegistry::current();$candidates=[];
        $query = $this->db->createQuery()->select(['p.id','p.plugin','pc.id AS promotion_code_id','pc.code','pc.params'])
            ->from($this->db->quoteName('#__dharmashop_cart_promotions','cp'))
            ->join('INNER',$this->db->quoteName('#__dharmashop_promotions','p'),'p.id=cp.promotion_id')
            ->join('LEFT',$this->db->quoteName('#__dharmashop_promotion_codes','pc'),'pc.id=cp.promotion_code_id')
            ->where('cp.cart_id=:cart');
        $cartId = (int) $cart->id;
        $query->bind(':cart', $cartId, ParameterType::INTEGER);
        $manual=$this->db->setQuery($query)->loadObject();if($manual)$candidates[(int)$manual->id]=['code'=>(string)$manual->code,'promotion_code_id'=>(int)$manual->promotion_code_id,'params'=>(string)$manual->params,'manual'=>true];
        $auto=$this->db->createQuery()->select(['id','plugin'])->from($this->db->quoteName('#__dharmashop_promotions'))->where('code IS NULL')->where('state=1')->order('ordering,id');
        foreach($this->db->setQuery($auto)->loadObjectList() as $row){$type=$registry->find((string)$row->plugin);if($type&&!$type->requiresCode())$candidates[(int)$row->id]=['code'=>null,'manual'=>false];}
        ksort($candidates);
        $locked=[];$skip=[];$hash=$this->customerHash($email);$buyer=$this->buyer($cart,$email,$userId);
        foreach($candidates as $id=>$candidate){
            $promotion=$this->db->setQuery(Sql::query($this->db,'SELECT id,code,state,access,starts,ends,params,plugin FROM #__dharmashop_promotions WHERE id=:id FOR UPDATE',[':id'=>[$id,ParameterType::INTEGER]]))->loadObject();
            if(!$promotion){if($candidate['manual'])throw new \DomainException(Text::_('COM_DHARMASHOP_PROMOTION_CODE_INVALID'));continue;}
            $settings=(new Registry((string)($candidate['params']??$promotion->params)))->toArray();
            if(!$this->isAvailable($promotion,$candidate['manual']?$settings:null)){if($candidate['manual'])throw new \DomainException(Text::_('COM_DHARMASHOP_PROMOTION_CODE_INVALID'));continue;}
            if(!$registry->find((string)$promotion->plugin)){if($candidate['manual'])throw new \DomainException(Text::_('COM_DHARMASHOP_PROMOTION_UNAVAILABLE'));continue;}
            $usageColumn=!empty($candidate['promotion_code_id'])?'promotion_code_id':'promotion_id';
            $usageId=(int)($candidate['promotion_code_id']??$id);
            $usageError=$this->usageError($usageColumn,$usageId,$settings,$buyer);
            if($usageError!==''){
                if($candidate['manual'])throw new \DomainException(Text::_($usageError));
                $skip[]=$id;continue;
            }
            $locked[$id]=['promotion_id'=>$id,'promotion_code_id'=>$candidate['promotion_code_id']??null,'code'=>$candidate['code'],'customer_hash'=>$hash];
        }
        return ['locked'=>$locked,'skip'=>$skip];
    }

    public function recordUse(array $use, int $orderId, ?int $customerId, float $amount): void
    {
        if (empty($use['promotion_id']) || $amount <= 0) return;
        $row = (object) [
            'promotion_id'=>(int)$use['promotion_id'],'promotion_code_id'=>isset($use['promotion_code_id'])?(int)$use['promotion_code_id']:null,'order_id'=>$orderId,'customer_id'=>$customerId,
            'customer_hash'=>$use['customer_hash'] ?: null,'code'=>$use['code'],'amount'=>round($amount,2),'created'=>Factory::getDate()->toSql(),
        ];
        $this->db->insertObject('#__dharmashop_promotion_uses', $row);
    }

    private function isAvailable(object $promotion,?array $ruleSettings=null): bool
    {
        if ((int)($promotion->state ?? 0)!==1) return false;
        $user=Factory::getApplication()->getIdentity();
        if (!in_array((int)($promotion->access ?? 1),array_map('intval',$user->getAuthorisedViewLevels()),true)) return false;
        $now = Factory::getDate()->toSql();
        $starts=array_key_exists('starts',$ruleSettings??[])?($ruleSettings['starts']??null):($promotion->starts??null);
        $ends=array_key_exists('ends',$ruleSettings??[])?($ruleSettings['ends']??null):($promotion->ends??null);
        return (empty($starts) || (string)$starts <= $now) && (empty($ends) || (string)$ends >= $now);
    }

    /** Лимиты проверяются одинаково при применении, показе итогов и под блокировкой заказа. */
    private function usageError(string $column, int $id, array $settings, array $buyer): string
    {
        $totalLimit = max(0, (int) ($settings['usage_limit'] ?? 0));
        if ($totalLimit > 0) {
            $used = (int) $this->db->setQuery(Sql::query($this->db,
                'SELECT COUNT(*) FROM #__dharmashop_promotion_uses WHERE ' . $column . '=:id',
                [':id'=>[$id,ParameterType::INTEGER]]))->loadResult();
            if ($used >= $totalLimit) return 'COM_DHARMASHOP_PROMOTION_USAGE_LIMIT';
        }
        $customerLimit = max(0, (int) ($settings['customer_limit'] ?? 0));
        if ($customerLimit < 1 || (!$buyer['hashes'] && !$buyer['customer_id'])) return '';
        $conditions = [];
        $bind = [':id'=>[$id,ParameterType::INTEGER]];
        if ($buyer['customer_id'] > 0) {
            $conditions[] = 'customer_id=:customer';
            $bind[':customer'] = [$buyer['customer_id'],ParameterType::INTEGER];
        }
        foreach ($buyer['hashes'] as $index => $hash) {
            $key = ':hash' . $index;
            $conditions[] = 'customer_hash=' . $key;
            $bind[$key] = [$hash];
        }
        $used = (int) $this->db->setQuery(Sql::query($this->db,
            'SELECT COUNT(*) FROM #__dharmashop_promotion_uses WHERE ' . $column . '=:id AND (' . implode(' OR ', $conditions) . ')', $bind))->loadResult();
        return $used >= $customerLimit ? 'COM_DHARMASHOP_PROMOTION_CUSTOMER_LIMIT' : '';
    }

    /** Учётная запись и уже введённый e-mail; гостевой адрес привязан к конкретной корзине и текущему пользователю. */
    private function buyer(object $cart, ?string $email = null, ?int $userId = null): array
    {
        $app = Factory::getApplication();
        $identity = $app->getIdentity();
        $userId ??= $identity && !$identity->guest ? (int) $identity->id : 0;
        if ($email === null && method_exists($app, 'getUserState')) {
            $saved = (array) $app->getUserState('com_dharmashop.checkout.promotion_buyer', []);
            if ((int) ($saved['cart_id'] ?? 0) === (int) $cart->id && (int) ($saved['user_id'] ?? -1) === $userId) {
                $email = (string) ($saved['email'] ?? '');
            }
        }
        $customerId = 0;
        $accountEmail = '';
        if ($userId > 0) {
            $customerId = (int) $this->db->setQuery(Sql::query($this->db,
                'SELECT id FROM #__dharmashop_customers WHERE user_id=:user', [':user'=>[$userId,ParameterType::INTEGER]]))->loadResult();
            $accountEmail = $identity && (int) $identity->id === $userId ? (string) $identity->email : (string) $this->db->setQuery(Sql::query($this->db,
                'SELECT email FROM #__users WHERE id=:user', [':user'=>[$userId,ParameterType::INTEGER]]))->loadResult();
        }
        $hashes = [];
        foreach ([$email ?? '', $accountEmail] as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL)) $hashes[] = $this->customerHash($address);
        }
        return ['customer_id'=>$customerId, 'hashes'=>array_values(array_unique($hashes))];
    }

    private function customerHash(string $email): string
    {
        $email = mb_strtolower(trim($email),'UTF-8');
        return $email === '' ? '' : hash_hmac('sha256',$email,Factory::getConfig()->get('secret'));
    }
}
