import { Controller } from '@hotwired/stimulus';

/*
 * What only the browser can do for the views with a time axis (timeline,
 * time grid): scroll to "now" (or to the configured scrollTime) when the view
 * first appears, and keep the "now" line moving between server renders.
 * Layout itself stays server-side: the windows value maps time to positions,
 * as [start ms, end ms, offset, size] fractions of the track.
 *
 * The axis value says which way time runs: "x" (timeline: the line moves
 * along --cal-left, the scroller scrolls sideways) or "y" (time grid: --cal-top,
 * up and down).
 */
export default class extends Controller {
    static targets = ['scroller', 'track', 'now'];
    static values = { windows: Array, scroll: Number, axis: { type: String, default: 'x' } };

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
        const vertical = this.axisValue === 'y';
        const size = vertical ? this.trackTarget.offsetHeight : this.trackTarget.offsetWidth;
        // A little room before the target, so "now" is not glued to the edge.
        const offset = Math.max(0, size * this.scrollValue - size / 24);
        if (vertical) {
            this.scrollerTarget.scrollTop = offset;
        } else {
            this.scrollerTarget.scrollLeft = offset;
        }
    }

    moveNow() {
        if (!this.hasNowTarget) {
            return;
        }
        const now = Date.now();
        const window = this.windowsValue.find(([start, end]) => now >= start && now < end);
        const property = this.axisValue === 'y' ? '--cal-top' : '--cal-left';
        this.nowTargets.forEach((line) => {
            if (!window) {
                line.hidden = true;

                return;
            }
            const [start, end, offset, size] = window;
            line.hidden = false;
            line.style.setProperty(property, `${(offset + size * (now - start) / (end - start)) * 100}%`);
        });
    }
}
