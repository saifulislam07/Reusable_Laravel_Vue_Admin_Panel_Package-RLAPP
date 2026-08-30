<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\Settings\FacebookMessengerSettingsRequest;
use App\Http\Requests\Admin\Settings\GoogleAnalyticsSettingsRequest;
use App\Http\Requests\Admin\Settings\GoogleSearchConsoleSettingsRequest;
use App\Http\Requests\Admin\Settings\SmtpSettingsRequest;
use App\Http\Requests\Admin\Settings\WhatsappSettingsRequest;
use App\Mail\Admin\SmtpTestMail;
use App\Support\Admin\IntegrationSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One page, five tabs (SMTP, Google Analytics, Google Search Console,
 * Facebook Messenger, WhatsApp) -- see admin::pages.settings.integrations.
 * Each tab posts to its own action so its validation stays independent of
 * the others, but they all redirect back to the same page with `?tab=` set
 * so the visitor lands back where they were.
 */
class IntegrationSettingsController extends AdminController
{
    public function __construct(private readonly IntegrationSettings $settings) {}

    public function edit(): View
    {
        return view('admin::pages.settings.integrations.index', [
            'smtp' => $this->settings->get('smtp'),
            'googleAnalytics' => $this->settings->get('google_analytics'),
            'googleSearchConsole' => $this->settings->get('google_search_console'),
            'facebookMessenger' => $this->settings->get('facebook_messenger'),
            'whatsapp' => $this->settings->get('whatsapp'),
        ]);
    }

    public function updateSmtp(SmtpSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // A blank password field means "leave it as it was" -- the saved
        // password is never echoed back into the form, so there is nothing
        // for the admin to have intentionally cleared.
        if (($data['password'] ?? '') === '') {
            $data['password'] = $this->settings->get('smtp')['password'] ?? null;
        }

        $this->settings->set('smtp', $data);

        return $this->redirectToTab('smtp', __('SMTP settings saved.'));
    }

    public function testSmtp(SmtpSettingsRequest $request): RedirectResponse
    {
        $this->updateSmtp($request);

        $recipient = $request->user();

        if (! $recipient) {
            return $this->redirectToTab('smtp');
        }

        try {
            $this->settings->applyRuntimeMailConfig();

            Mail::to($recipient)->send(new SmtpTestMail);

            $this->flashSuccess(__('Test email sent to :email.', ['email' => $recipient->email]));
        } catch (Throwable $e) {
            $this->flashError(__('Could not send the test email: :error', ['error' => $e->getMessage()]));
        }

        return $this->redirectToTab('smtp');
    }

    public function updateGoogleAnalytics(GoogleAnalyticsSettingsRequest $request): RedirectResponse
    {
        $this->settings->set('google_analytics', [
            'measurement_id' => $request->validated('measurement_id'),
            'enabled' => $request->boolean('enabled'),
        ]);

        return $this->redirectToTab('google-analytics', __('Google Analytics settings saved.'));
    }

    public function updateGoogleSearchConsole(GoogleSearchConsoleSettingsRequest $request): RedirectResponse
    {
        $this->settings->set('google_search_console', $request->validated());

        return $this->redirectToTab('google-search-console', __('Google Search Console settings saved.'));
    }

    public function updateFacebookMessenger(FacebookMessengerSettingsRequest $request): RedirectResponse
    {
        $this->settings->set('facebook_messenger', [
            'page_id' => $request->validated('page_id'),
            'app_id' => $request->validated('app_id'),
            'enabled' => $request->boolean('enabled'),
        ]);

        return $this->redirectToTab('facebook-messenger', __('Facebook Messenger settings saved.'));
    }

    public function updateWhatsapp(WhatsappSettingsRequest $request): RedirectResponse
    {
        $this->settings->set('whatsapp', [
            'number' => $request->validated('number'),
            'message' => $request->validated('message'),
            'enabled' => $request->boolean('enabled'),
        ]);

        return $this->redirectToTab('whatsapp', __('WhatsApp settings saved.'));
    }

    private function redirectToTab(string $tab, ?string $message = null): RedirectResponse
    {
        if ($message !== null) {
            $this->flashSuccess($message);
        }

        return redirect()->route('admin.settings.integrations.edit', ['tab' => $tab]);
    }
}
