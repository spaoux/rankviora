<?php

namespace App\Console\Commands;

use App\Models\ApiUsageLog;
use App\Seo\BudgetLedger;
use App\Seo\Money;
use App\Seo\SeoException;
use Illuminate\Console\Command;

class ReconcileSeoCost extends Command
{
    protected $signature = 'rankviora:seo-reconcile {receipt : API usage log ID}';

    protected $description = 'Yalnızca doğrulanmış sağlayıcı faturası ile belirsiz SEO maliyetini uzlaştırır; API çağırmaz';

    public function handle(BudgetLedger $ledger): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Etkileşimli doğrulama zorunlu.');

            return self::FAILURE;
        }
        $log = ApiUsageLog::whereIn('status', ['pending', 'uncertain'])->findOrFail($this->argument('receipt'));
        $this->line('Workspace #'.$log->workspace_id.' / '.$log->mode.' / '.$log->operation.' / Task '.($log->task_id ?? 'bilinmiyor'));
        $amount = (string) $this->ask('Sağlayıcı hesabından doğrulanan gerçekleşmiş tutar (USD; en fazla 6 ondalık)');
        $note = (string) $this->ask('Fatura/task referansı ve doğrulama notu (anahtar veya parola girmeyin)');
        if (! preg_match('/^\d{1,5}(\.\d{1,6})?$/D', $amount) || mb_strlen(trim($note)) < 5 || mb_strlen($note) > 2000) {
            $this->error('Geçersiz tutar veya denetim notu.');

            return self::FAILURE;
        }
        if (! $this->confirm('Bu tutarı sağlayıcı hesabından doğruladınız mı?', false)) {
            return self::FAILURE;
        }
        try {
            $ledger->reconcile($log->id, Money::micros((float) $amount), $note);
        } catch (SeoException $error) {
            $this->error($error->reason);

            return self::FAILURE;
        }
        $this->info('Maliyet uzlaştırıldı. Orijinal hata ve denetim notu saklandı. Yeni ücretli sorgu yapılmadı.');

        return self::SUCCESS;
    }
}
