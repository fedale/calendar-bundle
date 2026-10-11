import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/*
 * The UI settings dialog of a calendar: a native <dialog> whose body is
 * fetched from UiSettingsController. Changing the scope reloads the body,
 * submitting posts the form and shows the answer; when the answer carries the
 * "saved" marker, every calendar on the page re-renders with the new settings.
 *
 * The dialog is data-live-ignore: Live re-renders never touch it.
 */
export default class extends Controller {
    static targets = ['dialog', 'body'];
    static values = { url: String };

    async open() {
        if (!this.urlValue || !this.hasDialogTarget) {
            return;
        }
        await this.load(this.urlValue);
        this.dialogTarget.showModal();
    }

    close() {
        this.dialogTarget?.close();
    }

    switchScope(event) {
        const url = new URL(this.urlValue, window.location.origin);
        url.searchParams.set('scope', event.target.value);
        this.load(url.toString());
    }

    async submit(event) {
        event.preventDefault();
        const form = event.target;
        const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'text/html' } });
        this.bodyTarget.innerHTML = await response.text();
        if (this.bodyTarget.querySelector('[data-cal-ui-settings-saved]')) {
            this.refreshCalendars();
        }
    }

    async load(url) {
        const response = await fetch(url, { headers: { Accept: 'text/html' } });
        this.bodyTarget.innerHTML = await response.text();
    }

    // Settings saved for every calendar can change other calendars too.
    refreshCalendars() {
        document.querySelectorAll('.cal[data-controller~="live"]').forEach(async (element) => {
            (await getComponent(element)).render();
        });
    }
}
