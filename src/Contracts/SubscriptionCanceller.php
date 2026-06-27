<?php

namespace Lalalili\SubscriptionCore\Contracts;

use Lalalili\SubscriptionCore\Models\Subscription;

/**
 * 取消訂閱的可插拔接縫。
 *
 * 由 host 綁定實作，封裝「需要時連動金流取消（如綠界定期定額）後再標記本地取消」的流程，
 * 讓後台（subscription-filament）等通用層只依賴此契約、不直接耦合特定金流。
 */
interface SubscriptionCanceller
{
    /**
     * 取消訂閱。
     *
     * @return bool 成功取消（含已連動金流停止後續扣款）為 true；金流取消失敗為 false（此時不應標記本地取消）
     */
    public function cancel(Subscription $subscription): bool;
}
