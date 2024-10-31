<?php

namespace QuickSubmit;

use App\Classes\Plugin;
use App\Facades\SidebarFacade;
use Filament\Panel;
use QuickSubmit\Pages\QuickSubmitPage;

class QuickSubmitPlugin extends Plugin
{
    public function boot()
    {
        
    }

    public function onPanel(Panel $panel): void
    {
        $panel->pages([
            QuickSubmitPage::class,
        ]);
    }

    public function getPluginPage(): ?string
    {
        if(!app()->getCurrentScheduledConferenceId()) return null;

        try {
            return QuickSubmitPage::getUrl();
        } catch (\Throwable $th) {
            return null;
        }

    }
}