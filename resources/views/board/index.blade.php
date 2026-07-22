<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Board</h2>
                @if ($release)
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $release->name }} ·
                        <a href="{{ route('releases.show', $release) }}" class="text-indigo-600 hover:underline">back to release</a>
                    </p>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Filters --}}
            <form method="GET" action="{{ route('board.index') }}" class="rounded-xl bg-white p-4 shadow">
                <div class="grid gap-4 sm:grid-cols-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Release</label>
                        <select name="release_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All releases</option>
                            @foreach ($releases as $r)
                                <option value="{{ $r->id }}" @selected($filters['release_id'] === $r->id)>{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Assignee</label>
                        <select name="assignee_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Anyone</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected($filters['assignee_id'] === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2 sm:col-span-2">
                        <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-900">Apply</button>
                        <a href="{{ route('board.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">Reset</a>
                        <span class="ml-auto self-center text-xs text-gray-400">Drag cards between columns to change status.</span>
                    </div>
                </div>
            </form>

            {{-- Columns --}}
            <div class="flex gap-4 overflow-x-auto pb-4">
                @foreach ($statuses as $status => $label)
                    @php
                        $cards = $columns[$status];
                        $dot = match ($status) {
                            'todo' => 'bg-gray-400',
                            'in_progress' => 'bg-blue-500',
                            'in_review' => 'bg-amber-500',
                            'done' => 'bg-emerald-500',
                            default => 'bg-gray-400',
                        };
                    @endphp
                    <div class="board-column flex w-72 flex-none flex-col rounded-xl bg-gray-100/70 p-3" data-status="{{ $status }}">
                        <div class="mb-3 flex items-center gap-2 px-1">
                            <span class="h-2.5 w-2.5 rounded-full {{ $dot }}"></span>
                            <h3 class="text-sm font-semibold text-gray-700">{{ $label }}</h3>
                            <span class="board-count ml-auto rounded-full bg-white px-2 py-0.5 text-xs font-medium text-gray-500">{{ $cards->count() }}</span>
                        </div>
                        <div class="board-list flex min-h-[60px] flex-1 flex-col gap-2">
                            @foreach ($cards as $task)
                                @include('partials.board-card', ['task' => $task])
                            @endforeach
                            <p class="board-empty px-1 py-6 text-center text-xs text-gray-400" @if($cards->count()) style="display:none" @endif>Drop tasks here</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        (function () {
            const meta = document.querySelector('meta[name="csrf-token"]');
            const csrf = meta ? meta.content : '';
            let dragged = null;

            document.querySelectorAll('.board-card').forEach(bindCard);

            function bindCard(card) {
                card.addEventListener('dragstart', function (e) {
                    dragged = card;
                    setTimeout(() => card.classList.add('opacity-40'), 0);
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', card.dataset.taskId);
                });
                card.addEventListener('dragend', function () {
                    if (dragged) dragged.classList.remove('opacity-40');
                    dragged = null;
                    updateCounts();
                });
            }

            function getAfterElement(list, y) {
                const cards = [...list.querySelectorAll('.board-card:not(.opacity-40)')];
                return cards.reduce((closest, child) => {
                    const box = child.getBoundingClientRect();
                    const offset = y - box.top - box.height / 2;
                    if (offset < 0 && offset > closest.offset) return { offset, element: child };
                    return closest;
                }, { offset: -Infinity }).element || null;
            }

            document.querySelectorAll('.board-column').forEach(function (col) {
                const list = col.querySelector('.board-list');
                const empty = col.querySelector('.board-empty');
                col.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    if (!dragged) return;
                    if (empty) empty.style.display = 'none';
                    const after = getAfterElement(list, e.clientY);
                    if (after == null) list.insertBefore(dragged, empty);
                    else list.insertBefore(dragged, after);
                });
                col.addEventListener('drop', function (e) {
                    e.preventDefault();
                    if (!dragged) return;
                    const status = col.dataset.status;
                    const taskId = dragged.dataset.taskId;
                    const orderedIds = [...list.querySelectorAll('.board-card')].map(c => c.dataset.taskId);
                    persist(taskId, status, orderedIds);
                });
            });

            function persist(taskId, status, orderedIds) {
                fetch('/board/tasks/' + taskId, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ status: status, ordered_ids: orderedIds }),
                }).then(function (r) {
                    if (!r.ok) location.reload();
                }).catch(function () { location.reload(); });
            }

            function updateCounts() {
                document.querySelectorAll('.board-column').forEach(function (col) {
                    const n = col.querySelectorAll('.board-card').length;
                    const count = col.querySelector('.board-count');
                    const empty = col.querySelector('.board-empty');
                    if (count) count.textContent = n;
                    if (empty) empty.style.display = n === 0 ? '' : 'none';
                });
            }
        })();
    </script>
</x-app-layout>
