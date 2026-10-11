import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/*
 * Turns gestures into calendar intents: drag an event to move it, drag its
 * end to resize it, drag over empty time to select a range, click a
 * clickable event, press Delete on a deletable one.
 *
 * The browser decides nothing. It reads where the pointer is from the
 * "tracks" the server rendered (data-cal-track), computes the wall-clock
 * times under it the same way the server laid them out, and calls the
 * component's action. The server checks the intent, hands it to the domain
 * and re-renders: the preview here is only a visual hint until then, and an
 * accepted or rejected change both end with the real state on screen.
 *
 * Track kinds:
 * - "columns": day columns (month weeks, the all-day row), data-cal-columns
 *   = [[date, resource|null], ...];
 * - "y": a time-grid column, data-cal-date (+ data-cal-resource), with the
 *   day window in the view's data-cal-axis = {from, to} (minutes);
 * - "x": a timeline row, data-cal-resource, with the days in the view's
 *   data-cal-axis = {from, to, days: [[date, left, width], ...]}.
 *
 * Before calling the server, a cancelable "cal:intent" DOM event is
 * dispatched with the action and its arguments: preventDefault() aborts.
 */

const THRESHOLD = 4;
const DAY = 1440;

export default class extends Controller {
    static values = { snap: Number, selectable: Boolean, confirmDelete: String };

    connect() {
        this.handlers = {
            pointerdown: (event) => this.pointerDown(event),
            click: (event) => this.click(event),
            keydown: (event) => this.keyDown(event),
            'cal:open': (event) => this.open(event),
        };
        for (const [type, handler] of Object.entries(this.handlers)) {
            // Clicks are caught early, to swallow the one that ends a drag.
            this.element.addEventListener(type, handler, type === 'click');
        }
        this.move = (event) => this.pointerMove(event);
        this.up = (event) => this.pointerUp(event);
    }

    disconnect() {
        for (const [type, handler] of Object.entries(this.handlers)) {
            this.element.removeEventListener(type, handler, type === 'click');
        }
        this.cancel();
    }

    // Gestures

    pointerDown(event) {
        if (event.button !== 0 || event.target.closest('[popover], [data-cal-delete]')) {
            return;
        }
        const track = event.target.closest('[data-cal-track]');
        if (!track || !this.element.contains(track)) {
            return;
        }
        const item = event.target.closest('[data-cal-event]');
        let mode = null;
        if (item && event.target.closest('[data-cal-resize]')) {
            mode = 'resize';
        } else if (item && item.hasAttribute('data-cal-movable')) {
            mode = 'move';
        } else if (!item && this.selectableValue) {
            mode = 'select';
        }
        if (!mode) {
            return;
        }

        event.preventDefault();
        const rect = item ? item.getBoundingClientRect() : null;
        this.drag = {
            mode,
            track,
            item,
            x: event.clientX,
            y: event.clientY,
            grab: this.locate(track, event.clientX, event.clientY),
            width: rect?.width,
            height: rect?.height,
            active: false,
        };
        window.addEventListener('pointermove', this.move);
        window.addEventListener('pointerup', this.up);
        window.addEventListener('pointercancel', this.up);
    }

    pointerMove(event) {
        const drag = this.drag;
        const dx = event.clientX - drag.x;
        const dy = event.clientY - drag.y;
        if (!drag.active) {
            if (Math.hypot(dx, dy) < THRESHOLD) {
                return;
            }
            drag.active = true;
            this.element.classList.add('cal--dragging');
            drag.item?.classList.add('cal-event--dragging');
        }

        if (drag.mode === 'move') {
            drag.item.style.transform = `translate(${dx}px, ${dy}px)`;
        } else if (drag.mode === 'resize') {
            if (drag.track.dataset.calTrack === 'y') {
                drag.item.style.height = `${Math.max(8, drag.height + dy)}px`;
            } else {
                drag.item.style.width = `${Math.max(8, drag.width + dx)}px`;
            }
        } else {
            this.drawSelection(drag, this.locate(drag.track, event.clientX, event.clientY));
        }
    }

    pointerUp(event) {
        window.removeEventListener('pointermove', this.move);
        window.removeEventListener('pointerup', this.up);
        window.removeEventListener('pointercancel', this.up);
        const drag = this.drag;
        if (!drag?.active || event.type === 'pointercancel') {
            this.cancel();

            return;
        }
        // The click that follows a drag must not open the event's link.
        this.swallowClick = true;
        setTimeout(() => { this.swallowClick = false; }, 0);

        if (drag.mode === 'move') {
            this.dropMove(drag, event);
        } else if (drag.mode === 'resize') {
            this.dropResize(drag, event);
        } else {
            this.dropSelection(drag, this.locate(drag.track, event.clientX, event.clientY));
        }
    }

    dropMove(drag, event) {
        const target = this.trackAt(event.clientX, event.clientY, drag.track.dataset.calTrack);
        if (!target) {
            this.cancel();

            return;
        }
        const to = this.locate(target, event.clientX, event.clientY);
        const unit = this.unit(drag.track);
        const delta = Math.round((to.total - drag.grab.total) / unit) * unit;
        const item = drag.item.dataset;
        const fromResource = item.calResource ?? '';
        const toResource = to.resource ?? fromResource;
        if (delta === 0 && toResource === fromResource) {
            this.cancel();

            return;
        }
        const allDay = 'calAllDay' in item;
        this.intent('moveEvent', {
            source: item.calSource,
            id: item.calId,
            version: item.calVersion ?? '',
            start: format(parse(item.calStart) + delta, allDay),
            end: format(parse(item.calEnd) + delta, allDay),
            allDay: allDay ? '1' : '',
            fromResource,
            toResource,
        });
    }

    dropResize(drag, event) {
        const at = this.locate(drag.track, event.clientX, event.clientY);
        const item = drag.item.dataset;
        const allDay = 'calAllDay' in item;
        const unit = this.unit(drag.track);
        const start = parse(item.calStart);
        // On day columns the pointer is on the last day; on an axis, at the end time.
        const pointed = drag.track.dataset.calTrack === 'columns' ? at.total + DAY : Math.ceil(at.total / unit) * unit;
        const end = Math.max(start + unit, pointed);
        if (end === parse(item.calEnd)) {
            this.cancel();

            return;
        }
        this.intent('resizeEvent', {
            source: item.calSource,
            id: item.calId,
            version: item.calVersion ?? '',
            start: item.calStart,
            end: format(end, allDay),
            allDay: allDay ? '1' : '',
        });
    }

    dropSelection(drag, at) {
        const [start, end] = this.selectedRange(drag, at);
        const allDay = this.unit(drag.track) >= DAY;
        this.intent('select', {
            start: format(start, allDay),
            end: format(end, allDay),
            allDay: allDay ? '1' : '',
            resource: drag.grab.resource ?? '',
        });
    }

    // Clicks and keys

    click(event) {
        if (this.swallowClick) {
            event.preventDefault();
            event.stopPropagation();

            return;
        }
        const remove = event.target.closest('[data-cal-delete]');
        if (remove) {
            event.preventDefault();
            this.remove(remove.closest('[data-cal-event]'));

            return;
        }
        const item = event.target.closest('[data-cal-clickable]');
        if (item && !item.closest('[popover]')) {
            this.intent('clickEvent', { source: item.dataset.calSource, id: item.dataset.calId, version: item.dataset.calVersion ?? '' });
        }
    }

    keyDown(event) {
        const item = event.target.closest?.('[data-cal-event]');
        if (!item || event.target !== item) {
            return;
        }
        if ((event.key === 'Delete' || event.key === 'Backspace') && item.hasAttribute('data-cal-deletable')) {
            event.preventDefault();
            this.remove(item);
        } else if ((event.key === 'Enter' || event.key === ' ') && item.hasAttribute('data-cal-clickable')) {
            event.preventDefault();
            this.intent('clickEvent', { source: item.dataset.calSource, id: item.dataset.calId, version: item.dataset.calVersion ?? '' });
        }
    }

    remove(item) {
        if (this.confirmDeleteValue && !window.confirm(this.confirmDeleteValue)) {
            return;
        }
        this.intent('deleteEvent', { source: item.dataset.calSource, id: item.dataset.calId, version: item.dataset.calVersion ?? '' });
    }

    // An accepted intent may ask to open a URL (e.g. the application's form).
    open(event) {
        const { url, frame } = event.detail ?? {};
        if (!url) {
            return;
        }
        const target = frame ? document.getElementById(frame) : null;
        if (target) {
            target.src = url;
        } else if (window.Turbo) {
            window.Turbo.visit(url);
        } else {
            window.location.assign(url);
        }
    }

    // Server round trip

    async intent(action, args) {
        const allowed = this.element.dispatchEvent(new CustomEvent('cal:intent', {
            bubbles: true,
            cancelable: true,
            detail: { action, ...args },
        }));
        if (!allowed) {
            this.cancel();

            return;
        }
        // The preview stays until the answer arrives, then goes: Live keeps
        // changes made by scripts across re-renders, so it must be undone
        // here for the real state (moved, or back in place) to show.
        const drag = this.drag;
        this.drag = null;
        try {
            const component = await getComponent(this.element);
            await component.action(action, args);
        } finally {
            this.undoPreview(drag);
        }
    }

    cancel() {
        this.undoPreview(this.drag);
        this.drag = null;
    }

    undoPreview(drag) {
        if (drag?.item) {
            drag.item.style.removeProperty('transform');
            drag.item.style.removeProperty('height');
            drag.item.style.removeProperty('width');
            drag.item.classList.remove('cal-event--dragging');
        }
        drag?.selection?.remove();
        this.element.classList.remove('cal--dragging');
    }

    // Geometry: from the pointer to wall-clock minutes, as laid out by the server

    /** The wall-clock time (total minutes, see parse()) and resource under the pointer. */
    locate(track, x, y) {
        const rect = track.getBoundingClientRect();
        const kind = track.dataset.calTrack;
        if (kind === 'columns') {
            const columns = JSON.parse(track.dataset.calColumns);
            const index = clamp(Math.floor((x - rect.left) / rect.width * columns.length), 0, columns.length - 1);
            const [date, resource] = columns[index];

            return { total: parse(date), resource: resource ?? undefined, index };
        }
        const axis = JSON.parse(track.closest('[data-cal-axis]').dataset.calAxis);
        const resource = track.dataset.calResource;
        if (kind === 'y') {
            const fraction = clamp((y - rect.top) / rect.height, 0, 1);

            return { total: parse(track.dataset.calDate) + axis.from + fraction * (axis.to - axis.from), resource };
        }
        const fraction = clamp((x - rect.left) / rect.width, 0, 0.999999);
        const day = axis.days.find(([, left, width]) => fraction >= left && fraction < left + width) ?? axis.days[axis.days.length - 1];
        const local = clamp((fraction - day[1]) / day[2], 0, 1);

        return { total: parse(day[0]) + axis.from + local * (axis.to - axis.from), resource };
    }

    /** The track of the same kind under the pointer, for drops across rows and columns. */
    trackAt(x, y, kind) {
        return document.elementsFromPoint(x, y)
            .map((element) => element.closest('[data-cal-track]'))
            .find((track) => track && track.dataset.calTrack === kind && this.element.contains(track)) ?? null;
    }

    unit(track) {
        return track.dataset.calTrack === 'columns' ? DAY : this.snapValue;
    }

    selectedRange(drag, at) {
        const unit = this.unit(drag.track);
        const low = Math.min(drag.grab.total, at.total);
        const high = Math.max(drag.grab.total, at.total);
        const start = Math.floor(low / unit) * unit;
        const end = drag.track.dataset.calTrack === 'columns'
            ? high + DAY
            : Math.max(start + unit, Math.ceil(high / unit) * unit);

        return [start, end];
    }

    drawSelection(drag, at) {
        if (!drag.selection) {
            drag.selection = document.createElement('div');
            drag.selection.className = 'cal-selection';
            drag.track.append(drag.selection);
        }
        const kind = drag.track.dataset.calTrack;
        const style = drag.selection.style;
        if (kind === 'columns') {
            const count = JSON.parse(drag.track.dataset.calColumns).length;
            const first = Math.min(drag.grab.index, at.index);
            const last = Math.max(drag.grab.index, at.index);
            Object.assign(style, { left: `${first / count * 100}%`, width: `${(last - first + 1) / count * 100}%`, top: '0', bottom: '0' });

            return;
        }
        const [start, end] = this.selectedRange(drag, at);
        const axis = JSON.parse(drag.track.closest('[data-cal-axis]').dataset.calAxis);
        if (kind === 'y') {
            const base = parse(drag.track.dataset.calDate) + axis.from;
            const span = axis.to - axis.from;
            Object.assign(style, { top: `${(start - base) / span * 100}%`, height: `${(end - start) / span * 100}%`, left: '0', right: '0' });

            return;
        }
        const left = position(axis, start);
        Object.assign(style, { left: `${left * 100}%`, width: `${(position(axis, end) - left) * 100}%`, top: '0', bottom: '0' });
    }
}

/** Minutes since 1970-01-01 00:00 of a wall-clock "Y-m-d" or "Y-m-dTH:i" (no timezone involved). */
function parse(value) {
    const [date, time = '00:00'] = value.split('T');
    const [year, month, day] = date.split('-').map(Number);
    const [hours, minutes] = time.split(':').map(Number);

    return Date.UTC(year, month - 1, day) / 60000 + hours * 60 + minutes;
}

function format(total, dateOnly) {
    const moment = new Date(Math.round(total) * 60000);
    const pad = (number) => String(number).padStart(2, '0');
    const date = `${moment.getUTCFullYear()}-${pad(moment.getUTCMonth() + 1)}-${pad(moment.getUTCDate())}`;

    return dateOnly ? date : `${date}T${pad(moment.getUTCHours())}:${pad(moment.getUTCMinutes())}`;
}

/** Where a wall-clock time falls on a timeline axis, as a fraction (the inverse of locate()). */
function position(axis, total) {
    const date = format(total, true);
    const day = axis.days.find(([value]) => value === date);
    if (!day) {
        return total < parse(axis.days[0][0]) ? 0 : 1;
    }
    const minutes = clamp(total - parse(date), axis.from, axis.to);

    return day[1] + day[2] * (minutes - axis.from) / (axis.to - axis.from);
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}
