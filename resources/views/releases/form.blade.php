@php
    use App\Models\Release;
    $phaseColors = Release::PHASE_COLORS;
@endphp

<div class="space-y-8" x-data="releaseForm()">
    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Release name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $release->name) }}" required
                   placeholder="e.g. v2.4 Checkout revamp"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="year" class="block text-sm font-medium text-gray-700">Year</label>
                <input id="year" name="year" type="number" min="2000" max="2100" value="{{ old('year', $release->year) }}" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('year') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="quarter" class="block text-sm font-medium text-gray-700">Quarter</label>
                <select id="quarter" name="quarter" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ([1,2,3,4] as $q)
                        <option value="{{ $q }}" @selected((int) old('quarter', $release->quarter) === $q)>Q{{ $q }}</option>
                    @endforeach
                </select>
                @error('quarter') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-gray-700">Description <span class="text-gray-400">(optional)</span></label>
        <textarea id="description" name="description" rows="3"
                  placeholder="What's in this release, goals, notes…"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $release->description) }}</textarea>
        @error('description') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <label for="project_id" class="block text-sm font-medium text-gray-700">Project</label>
            <select id="project_id" name="project_id" required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">— Select a project —</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected((int) old('project_id', $release->project_id) === $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
            @error('project_id') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="team_id" class="block text-sm font-medium text-gray-700">Owning team</label>
            <select id="team_id" name="team_id" required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">— Select a team —</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}" @selected((int) old('team_id', $release->team_id) === $team->id)>{{ $team->name }}</option>
                @endforeach
            </select>
            @error('team_id') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700">Overall window</h3>
        <p class="mt-1 text-xs text-gray-500">The full period this release occupies the team. Phases must sit inside this window.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700">Start date</label>
                <input id="start_date" name="start_date" type="date" x-model="start"
                       value="{{ old('start_date', optional($release->start_date)->toDateString()) }}" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('start_date') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700">End date</label>
                <input id="end_date" name="end_date" type="date" x-model="end"
                       value="{{ old('end_date', optional($release->end_date)->toDateString()) }}" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('end_date') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 p-5">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Phases</h3>
                <p class="mt-1 text-xs text-gray-500">Development → QA → Retest → Release, each with its own dates.</p>
            </div>
            <button type="button" @click="autofill()"
                    class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                Auto-split window evenly
            </button>
        </div>

        <div class="mt-4 space-y-3">
            @foreach (Release::PHASES as $key => $label)
                @php $pv = ($phaseValues[$key] ?? null); @endphp
                <div class="grid items-center gap-3 sm:grid-cols-[160px_1fr_1fr]">
                    <div class="flex items-center gap-2">
                        <span class="h-3 w-3 rounded-full" style="background-color: {{ $phaseColors[$key] }}"></span>
                        <span class="text-sm font-medium text-gray-700">{{ $label }}</span>
                    </div>
                    <div>
                        <input type="date" name="phases[{{ $key }}][start]" x-ref="{{ $key }}_start"
                               value="{{ old("phases.$key.start", optional($pv?->start_date)->toDateString()) }}" required
                               class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error("phases.$key.start") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <input type="date" name="phases[{{ $key }}][end]" x-ref="{{ $key }}_end"
                               value="{{ old("phases.$key.end", optional($pv?->end_date)->toDateString()) }}" required
                               class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error("phases.$key.end") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<script>
    function releaseForm() {
        return {
            start: @json(old('start_date', optional($release->start_date)->toDateString())),
            end: @json(old('end_date', optional($release->end_date)->toDateString())),
            phases: @json(array_keys(Release::PHASES)),
            autofill() {
                if (!this.start || !this.end) {
                    alert('Set the overall start and end dates first.');
                    return;
                }
                const start = new Date(this.start + 'T00:00:00');
                const end = new Date(this.end + 'T00:00:00');
                const totalDays = Math.floor((end - start) / 86400000) + 1;
                if (totalDays < this.phases.length) {
                    alert('The window is too short to split into four phases.');
                    return;
                }
                const per = Math.floor(totalDays / this.phases.length);
                let cursor = new Date(start);
                this.phases.forEach((key, i) => {
                    const isLast = i === this.phases.length - 1;
                    const pStart = new Date(cursor);
                    let pEnd = new Date(cursor);
                    pEnd.setDate(pEnd.getDate() + (isLast ? (totalDays - per * i) : per) - 1);
                    if (isLast) pEnd = new Date(end);
                    this.$refs[key + '_start'].value = pStart.toISOString().slice(0, 10);
                    this.$refs[key + '_end'].value = pEnd.toISOString().slice(0, 10);
                    cursor = new Date(pEnd);
                    cursor.setDate(cursor.getDate() + 1);
                });
            },
        };
    }
</script>
