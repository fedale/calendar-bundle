import { Controller } from '@hotwired/stimulus';

/*
 * A custom toolbar button declared with an "event": dispatches that DOM event
 * (bubbling, with the calendar, button key, view and date as detail), for the
 * application's own JavaScript to handle.
 */
export default class extends Controller {
    static values = { event: String, detail: Object };

    dispatch() {
        this.element.dispatchEvent(new CustomEvent(this.eventValue, { bubbles: true, detail: this.detailValue }));
    }
}
