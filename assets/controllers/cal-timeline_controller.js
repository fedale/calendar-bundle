import { Controller } from '@hotwired/stimulus';

/*
 * Timeline helpers that only the browser can do: scroll the track to "now"
 * (or to the configured scrollTime) when the view first appears, and keep the
 * "now" line moving between server renders. Layout itself stays server-side:
 * the windows value maps time to positions, as [start ms, end ms, left, width].
 */
export default class extends Controller {
    static targets = ['scroller', 'track', 'now'];
    static values = { windows: Array, scroll: Number };

    connect() {
        this.scrollToStart();
        this.timer = setInterval(() => this.moveNow(), 60 * 1000);
    }

    disconnect() {
        clearInterval(this.timer);
    }

    windowsValueChanged() {
        this.moveNow();
    }

    scrollToStart() {
        if (!this.hasScrollerTarget || !this.hasTrackTarget) {
            return;
        }
        // A little room before the target, so "now" is not glued to the edge.
        const offset = this.trackTarget.offsetWidth * this.scrollValue - this.trackTarget.offsetWidth / 48;
        this.scrollerTarget.scrollLeft = Math.max(0, offset);
    }

    moveNow() {
        if (!this.hasNowTarget) {
            return;
        }
        const now = Date.now();
        const window = this.windowsValue.find(([start, end]) => now >= start && now < end);
        if (!window) {
            this.nowTarget.hidden = true;

            return;
        }
        const [start, end, left, width] = window;
        this.nowTarget.hidden = false;
        this.nowTarget.style.setProperty('--cal-left', `${(left + width * (now - start) / (end - start)) * 100}%`);
    }
}
