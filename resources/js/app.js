// Keyboard navigation for the task table (PRD §9: keyboard-friendly for fast task entry).
// Arrow keys move between cells (Up/Down jump to the same column in the
// previous/next data row), Enter opens Edit on the focused row, Delete opens
// the delete confirmation. Implemented as an Alpine component, which Livewire 4
// already bundles.

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    if (typeof Alpine === 'undefined') {
        return;
    }

    Alpine.data('taskTableKeyboard', () => ({
        cells() {
            return Array.from(document.querySelectorAll('td[data-key-cell]'))
                .filter((cell) => cell instanceof HTMLElement);
        },

        rowOf(cell) {
            return cell instanceof HTMLElement ? cell.closest('[data-key-row]') : null;
        },

        onKeydown(event) {
            const cell = event.target instanceof HTMLElement
                ? event.target.closest('td[data-key-cell]')
                : null;

            if (! (cell instanceof HTMLElement)) {
                return;
            }

            const key = event.key;
            const row = this.rowOf(cell);

            // Enter / Space activate the row's Edit action; Delete its Delete action.
            if (key === 'Enter' || key === ' ') {
                if (event.target === cell) {
                    const edit = row?.querySelector('[data-action="edit"]');

                    if (edit instanceof HTMLElement) {
                        event.preventDefault();
                        edit.click();
                    }
                }

                return;
            }

            if (key === 'Delete') {
                const remove = row?.querySelector('[data-action="delete"]');

                if (remove instanceof HTMLElement) {
                    event.preventDefault();
                    remove.click();
                }

                return;
            }

            if (key !== 'ArrowUp' && key !== 'ArrowDown' && key !== 'ArrowLeft' && key !== 'ArrowRight') {
                return;
            }

            const cells = this.cells();
            const index = cells.indexOf(cell);

            if (index === -1) {
                return;
            }

            let target = null;

            if (key === 'ArrowLeft') {
                target = cells[index - 1] ?? null;
            } else if (key === 'ArrowRight') {
                target = cells[index + 1] ?? null;
            } else {
                // Up/Down: find the nearest earlier/later cell with the same
                // column index (data-key-col) in a different row.
                const column = cell.dataset.keyCol ?? '';
                const step = key === 'ArrowUp' ? -1 : 1;
                const ownRow = this.rowOf(cell);
                let cursor = index + step;

                while (cursor >= 0 && cursor < cells.length) {
                    const candidate = cells[cursor];

                    if ((candidate.dataset?.keyCol ?? '') === column && this.rowOf(candidate) !== ownRow) {
                        target = candidate;

                        break;
                    }

                    cursor += step;
                }
            }

            if (target instanceof HTMLElement && target !== cell) {
                event.preventDefault();
                target.focus();
            }
        },
    }));
});
