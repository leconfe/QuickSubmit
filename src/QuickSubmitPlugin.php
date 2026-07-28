<?php

namespace QuickSubmit;

use App\Classes\Plugin;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;
use QuickSubmit\Pages\QuickSubmitPage;
use QuickSubmit\Pages\QuickSubmitPageV3;
use RuntimeException;

class QuickSubmitPlugin extends Plugin
{
    public function boot()
    {
        //
    }

    public function onPanel(Panel $panel): void
    {
        $panel->pages([
            $this->resolvePageClass(),
        ]);
    }

    public function getPluginPage(): ?string
    {
        if (! app()->getCurrentScheduledConferenceId()) {
            return null;
        }

        try {
            $page = $this->resolvePageClass();

            return $page::getUrl();
        } catch (\Throwable $th) {
            return null;
        }
    }

    /**
     * @return class-string<Page>
     */
    public function resolvePageClass(): string
    {
        if (class_exists(Schema::class)) {
            return QuickSubmitPage::class;
        }

        if (class_exists(Form::class)) {
            return QuickSubmitPageV3::class;
        }

        throw new RuntimeException('Quick Submit requires Filament 3 or Filament 5.');
    }
}
