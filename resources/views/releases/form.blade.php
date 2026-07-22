@php
    use App\Models\Release;
    $phaseColors = Release::PHASE_COLORS;
@endphp

<div class="space-y-8" x-data="releaseForm(@js($offDayValues ?? []))">
    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Release name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $release->name) }}" required
                   placeholder="e.g. v2.4 Checkout revamp"
                   class="field-input">
            @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="year" class="block text-sm font-medium text-slate-700">Year</label>
                <input id="year" name="year" type="number" min="2000" max="2100" value="{{ old('year', $release->year) }}" required
                       class="field-input">
                @error('year') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="quarter" class="block text-sm font-medium text-slate-700">Quarter</label>
                <select id="quarter" name="quarter" required
                        class="field-input">
                    @foreach ([1,2,3,4] as $q)
                        <option value="{{ $q }}" @selected((int) old('quarter', $release->quarter) === $q)>Q{{ $q }}</option>
                    @endforeach
                </select>
                @error('quarter') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-slate-700">Description <span class="text-slate-400">(optional)</span></label>
        <textarea id="description" name="description" rows="3"
                  placeholder="What's in this release, goals, notes…"
                  class="field-input">{{ old('description', $release->description) }}</textarea>
        @error('description') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <label for="project_id" class="block text-sm font-medium text-slate-700">Project</label>
            <select id="project_id" name="project_id" required
                    class="field-input">
                <option value="">— Select a project —</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected((int) old('project_id', $release->project_id) === $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
            @error('project_id') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="team_id" class="block text-sm font-medium text-slate-700">Owning team</label>
            <select id="team_id" name="team_id" required
                    class="field-input">
                <option value="">— Select a team —</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}" @selected((int) old('team_id', $release->team_id) === $team->id)>{{ $team->name }}</option>
                @endforeach
            </select>
            @error('team_id') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 p-5">
        <h3 class="text-sm font-semibold text-slate-700">Overall window</h3>
        <p class="mt-1 text-xs text-slate-500">The full period this release occupies the team. Phases must sit inside this window.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="start_date" class="block text-sm font-medium text-slate-700">Start date</label>
                <input id="start_date" name="start_date" type="date" x-model="start"
                       value="{{ old('start_date', optional($release->start_date)->toDateString()) }}" required
                       class="field-input">
                @error('start_date') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-slate-700">End date</label>
                <input id="end_date" name="end_date" type="date" x-model="end"
                       value="{{ old('end_date', optional($release->end_date)->toDateString()) }}" required
                       class="field-input">
                @error('end_date') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-700">Phases</h3>
                <p class="mt-1 text-xs text-slate-500">Development → QA → Retest → Release, each with its own dates.</p>
            </div>
            <button type="button" @click="autofill()"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                Auto-split window evenly
            </button>
        </div>

        <div class="mt-4 space-y-3">
            @foreach (Release::PHASES as $key => $label)
                @php $pv = ($phaseValues[$key] ?? null); @endphp
                <div class="grid items-center gap-3 sm:grid-cols-[160px_1fr_1fr]">
                    <div class="flex items-center gap-2">
                        <span class="h-3 w-3 rounded-full" style="background-color: {{ $phaseColors[$key] }}"></span>
                        <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
                    </div>
                    <div>
                        <input type="date" name="phases[{{ $key }}][start]" x-ref="{{ $key }}_start"
                               value="{{ old("phases.$key.start", optional($pv?->start_date)->toDateString()) }}" required
                               class="field-input">
                        @error("phases.$key.start") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <input type="date" name="phases[{{ $key }}][end]" x-ref="{{ $key }}_end"
                               value="{{ old("phases.$key.end", optional($pv?->end_date)->toDateString()) }}" required
                               class="field-input">
                        @error("phases.$key.end") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Off-days --}}
    <div class="rounded-lg border border-slate-200 p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-slate-700">Off-days <span class="font-normal text-slate-400">(optional)</span></h3>
                <p class="mt-1 text-xs text-slate-500">Non-working days inside the window. They don't block scheduling — they reduce the working-day count.</p>
            </div>
            <div class="flex gap-2">
                <button type="button" @click="markWeekends()" class="btn-secondary btn-sm">Mark weekends</button>
                <button type="button" @click="addOffDay()" class="btn-secondary btn-sm">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/></svg>
                    Add off-day
                </button>
            </div>
        </div>

        <p x-show="offDays.length === 0" class="mt-4 text-xs text-slate-400">No off-days yet. Add specific dates or mark all weekends in the window.</p>
        <div class="mt-4 space-y-2">
            <template x-for="(od, i) in offDays" :key="i">
                <div class="grid items-center gap-3 sm:grid-cols-[210px_1fr_auto]">
                    <input type="date" :name="'off_days['+i+'][date]'" x-model="od.date" :min="start || null" :max="end || null" required class="field-input !mt-0">
                    <input type="text" :name="'off_days['+i+'][reason]'" x-model="od.reason" placeholder="Reason (optional)" class="field-input !mt-0">
                    <button type="button" @click="removeOffDay(i)" class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600" aria-label="Remove off-day">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
    function releaseForm(initialOffDays) {
        const fmt = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        const toast = (type, message) => window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } }));
        return {
            start: @json(old('start_date', optional($release->start_date)->toDateString())),
            end: @json(old('end_date', optional($release->end_date)->toDateString())),
            phases: @json(array_keys(Release::PHASES)),
            offDays: initialOffDays || [],
            addOffDay() { this.offDays.push({ date: '', reason: '' }); },
            removeOffDay(i) { this.offDays.splice(i, 1); },
            markWeekends() {
                if (!this.start || !this.end) { toast('warning', 'Set the overall start and end dates first.'); return; }
                const start = new Date(this.start + 'T00:00:00');
                const end = new Date(this.end + 'T00:00:00');
                const existing = new Set(this.offDays.map((o) => o.date));
                let added = 0;
                for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                    const iso = fmt(d);
                    if ((d.getDay() === 0 || d.getDay() === 6) && !existing.has(iso)) {
                        this.offDays.push({ date: iso, reason: 'Weekend' });
                        existing.add(iso);
                        added++;
                    }
                }
                toast(added ? 'success' : 'info', added ? (added + ' weekend day(s) added.') : 'All weekends are already marked.');
            },
            autofill() {
                if (!this.start || !this.end) { toast('warning', 'Set the overall start and end dates first.'); return; }
                const start = new Date(this.start + 'T00:00:00');
                const end = new Date(this.end + 'T00:00:00');
                const totalDays = Math.floor((end - start) / 86400000) + 1;
                if (totalDays < this.phases.length) { toast('warning', 'The window is too short to split into four phases.'); return; }
                const per = Math.floor(totalDays / this.phases.length);
                let cursor = new Date(start);
                this.phases.forEach((key, i) => {
                    const isLast = i === this.phases.length - 1;
                    const pStart = new Date(cursor);
                    let pEnd = new Date(cursor);
                    pEnd.setDate(pEnd.getDate() + (isLast ? (totalDays - per * i) : per) - 1);
                    if (isLast) pEnd = new Date(end);
                    this.$refs[key + '_start'].value = fmt(pStart);
                    this.$refs[key + '_end'].value = fmt(pEnd);
                    cursor = new Date(pEnd);
                    cursor.setDate(cursor.getDate() + 1);
                });
            },
        };
    }
</script>
