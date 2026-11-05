<section class="cia-readiness" aria-labelledby="cia-readiness-title">
    <header class="cia-readiness-header">
        <div>
            <h2 id="cia-readiness-title">Student score card</h2>
            <p>Track placement and interview readiness across your key milestones.</p>
        </div>
        <button id="cia-readiness-edit" type="button">Update scores</button>
    </header>
    <p id="cia-readiness-status" role="status" aria-live="polite">Loading your readiness scores...</p>
    <div class="cia-readiness-cards">
        <article class="cia-readiness-card" aria-labelledby="cia-placement-title">
            <h3 id="cia-placement-title">Placement Readiness</h3>
            <div class="cia-readiness-summary">
                <div id="cia-placement-score-ring" class="cia-readiness-ring">
                    <span id="cia-placement-score">0%</span>
                </div>
                <span>Placement Readiness Score (%)</span>
            </div>
            <div id="cia-placement-breakdown" class="cia-readiness-breakdown"></div>
        </article>
        <article class="cia-readiness-card" aria-labelledby="cia-interview-title">
            <h3 id="cia-interview-title">Interview Readiness</h3>
            <div class="cia-readiness-summary">
                <div id="cia-interview-score-ring" class="cia-readiness-ring">
                    <span id="cia-interview-score">0%</span>
                </div>
                <span>Interview Readiness Score (%)</span>
            </div>
            <div id="cia-interview-breakdown" class="cia-readiness-breakdown"></div>
        </article>
    </div>

    <dialog id="cia-readiness-dialog" class="cia-readiness-dialog" aria-labelledby="cia-readiness-dialog-title">
        <form id="cia-readiness-form">
            @csrf
            <header>
                <div>
                    <h3 id="cia-readiness-dialog-title">Update readiness metrics</h3>
                    <p>Enter scores from 0 to 100. Weighted totals are calculated automatically.</p>
                </div>
                <button id="cia-readiness-close" type="button" aria-label="Close score editor">×</button>
            </header>

            <fieldset>
                <legend>Placement readiness</legend>
                <label>Project completion
                    <input name="project_completion_score" type="number" min="0" max="100" step="0.01" value="0" required>
                </label>
                <label>Assessment scores
                    <input name="assessment_score" type="number" min="0" max="100" step="0.01" value="0" required>
                </label>
                <label>CIA usage
                    <input name="cia_usage_score" type="number" min="0" max="100" step="0.01" value="0" required>
                </label>
                <label>Documentation
                    <input name="docs_score" type="number" min="0" max="100" step="0.01" value="0" required>
                </label>
            </fieldset>

            <fieldset>
                <legend>Interview readiness</legend>
                <label>Viva scores
                    <input name="viva_score" type="number" min="0" max="100" step="0.01" value="0" required>
                </label>
                <label>Code review
                    <input name="code_review_score" type="number" min="0" max="100" step="0.01" value="0" required>
                </label>
                <label>Demo clarity
                    <input name="demo_clarity_score" type="number" min="0" max="100" step="0.01" value="0" required>
                </label>
                <label>Git quality
                    <input name="git_quality_score" type="number" min="0" max="100" step="0.01" value="0" required>
                </label>
            </fieldset>

            <div class="cia-readiness-actions">
                <button type="submit">Calculate and save</button>
                <button id="cia-readiness-cancel" type="button">Cancel</button>
            </div>
        </form>
    </dialog>
</section>

<section class="cia-skill-gap" aria-labelledby="cia-skill-gap-title">
    <h2 id="cia-skill-gap-title">Skill gap analysis</h2>

    <form id="cia-skill-gap-form">
        @csrf
        <label for="cia-target-role">Target role</label>
        <input
            id="cia-target-role"
            name="target_role"
            type="text"
            value="Full Stack Developer"
            maxlength="120"
            required
        >
        <button type="submit">Analyze skill gap</button>
    </form>

    <p id="cia-skill-gap-status" role="status" aria-live="polite"></p>
    <p id="cia-skill-gap-score"></p>
    <ul id="cia-skill-gap-list"></ul>
    <div style="max-width: 640px;">
        <canvas id="cia-skill-gap-radar" aria-label="Candidate skills compared with target-role benchmarks"></canvas>
    </div>
</section>

<section class="cia-career-recommendations" aria-labelledby="cia-career-recommendations-title">
    <h2 id="cia-career-recommendations-title">Career recommendations</h2>
    <form id="cia-career-recommendations-form">
        @csrf
        <label for="cia-career-skills">Your skills (comma separated)</label>
        <input id="cia-career-skills" name="skills" type="text" maxlength="1000">

        <label for="cia-career-gaps">Skills to improve (comma separated)</label>
        <input id="cia-career-gaps" name="skill_gaps" type="text" maxlength="1000">

        <label for="cia-career-target-role">Career goal</label>
        <input id="cia-career-target-role" name="target_role" type="text" maxlength="120" required>

        <button type="submit">Recommend career paths</button>
    </form>
    <p id="cia-career-recommendations-status" role="status" aria-live="polite"></p>
    <div id="cia-career-recommendations-results" class="cia-career-paths"></div>
</section>

<section class="cia-learning-path" aria-labelledby="cia-learning-path-title">
    <h2 id="cia-learning-path-title">8-week learning path</h2>
    <form id="cia-learning-path-form">
        @csrf
        <label for="cia-learning-path-target-role">Target role</label>
        <input
            id="cia-learning-path-target-role"
            name="target_role"
            type="text"
            maxlength="120"
            required
        >

        <label for="cia-learning-path-skills">Your skills (comma separated)</label>
        <input id="cia-learning-path-skills" name="skills" type="text" maxlength="1000">

        <label for="cia-learning-path-gaps">Skills to improve (comma separated)</label>
        <input id="cia-learning-path-gaps" name="skill_gaps" type="text" maxlength="1000">

        <label for="cia-learning-path-hours">Study hours per week</label>
        <input
            id="cia-learning-path-hours"
            name="weekly_hours"
            type="number"
            min="1"
            max="40"
            value="7"
            required
        >

        <button type="submit">Generate learning path</button>
    </form>
    <p id="cia-learning-path-status" role="status" aria-live="polite"></p>
    <div id="cia-learning-path-chart" class="cia-learning-path-chart" aria-label="Eight-week learning path timeline"></div>
    <div id="cia-learning-path-results" class="cia-learning-path-weeks"></div>
</section>

<section class="cia-role-matching" aria-labelledby="cia-role-matching-title">
    <h2 id="cia-role-matching-title">Internship &amp; role matching</h2>
    <form id="cia-role-matching-form">
        @csrf
        <label for="cia-role-matching-skills">Your skills (comma separated)</label>
        <input
            id="cia-role-matching-skills"
            name="skills"
            type="text"
            maxlength="1000"
            required
        >
        <button type="submit">Find matching roles</button>
    </form>
    <p id="cia-role-matching-status" role="status" aria-live="polite"></p>
    <div id="cia-role-matching-results" class="cia-role-matches"></div>
</section>

<style>
    .cia-readiness {
        margin-block: 2rem;
    }

    .cia-readiness-header,
    .cia-readiness-dialog header {
        align-items: center;
        display: flex;
        gap: 1rem;
        justify-content: space-between;
    }

    .cia-readiness-cards {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 22rem), 1fr));
    }

    .cia-readiness-card {
        border: 1px solid #d1d5db;
        border-radius: 0.75rem;
        padding: 1.25rem;
    }

    .cia-readiness-summary {
        align-items: center;
        display: flex;
        gap: 1rem;
        margin-block: 1rem;
    }

    .cia-readiness-ring {
        align-items: center;
        background: conic-gradient(
            var(--cia-readiness-color, #2563eb) 0% var(--cia-readiness-score, 0%),
            #e5e7eb var(--cia-readiness-score, 0%) 100%
        );
        border-radius: 50%;
        display: flex;
        flex: 0 0 6rem;
        height: 6rem;
        justify-content: center;
        position: relative;
        width: 6rem;
    }

    .cia-readiness-ring::before {
        background: white;
        border-radius: 50%;
        content: "";
        inset: 0.4rem;
        position: absolute;
    }

    .cia-readiness-ring span {
        font-size: 1.15rem;
        font-weight: 700;
        position: relative;
    }

    .cia-readiness-breakdown {
        display: grid;
        gap: 0.75rem;
    }

    .cia-readiness-metric {
        display: grid;
        gap: 0.35rem;
        grid-template-columns: 1fr auto;
    }

    .cia-readiness-metric progress {
        grid-column: 1 / -1;
        height: 0.65rem;
        width: 100%;
    }

    .cia-readiness-metric .is-high {
        accent-color: #15803d;
    }

    .cia-readiness-metric .is-medium {
        accent-color: #ca8a04;
    }

    .cia-readiness-metric .is-low {
        accent-color: #dc2626;
    }

    .cia-readiness-dialog {
        border: 0;
        border-radius: 0.75rem;
        max-height: 90vh;
        max-width: 40rem;
        padding: 1.5rem;
        width: calc(100% - 2rem);
    }

    .cia-readiness-dialog::backdrop {
        background: rgb(15 23 42 / 55%);
    }

    .cia-readiness-dialog form {
        display: grid;
        gap: 1rem;
    }

    .cia-readiness-dialog fieldset {
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        display: grid;
        gap: 0.75rem;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr));
        padding: 1rem;
    }

    .cia-readiness-dialog label {
        display: grid;
        gap: 0.35rem;
    }

    .cia-readiness-dialog input {
        min-width: 0;
        padding: 0.55rem;
        width: 100%;
    }

    .cia-readiness-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    @media (max-width: 30rem) {
        .cia-readiness-header {
            align-items: flex-start;
            flex-direction: column;
        }
    }

    .cia-career-recommendations {
        margin-block: 2rem;
    }

    .cia-career-recommendations form {
        display: grid;
        gap: 0.75rem;
        max-width: 42rem;
    }

    .cia-career-recommendations input {
        padding: 0.6rem;
    }

    .cia-career-recommendations button {
        justify-self: start;
        padding: 0.65rem 1rem;
    }

    .cia-career-paths {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 18rem), 1fr));
        margin-top: 1rem;
    }

    .cia-career-path {
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        padding: 1rem;
    }

    .cia-learning-path {
        margin-block: 2rem;
    }

    .cia-learning-path form {
        display: grid;
        gap: 0.75rem;
        max-width: 42rem;
    }

    .cia-learning-path input {
        padding: 0.6rem;
    }

    .cia-learning-path button {
        justify-self: start;
        padding: 0.65rem 1rem;
    }

    .cia-learning-path-chart,
    .cia-learning-week {
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        margin-top: 1rem;
        padding: 1rem;
    }

    .cia-learning-path-chart svg {
        max-width: 100%;
    }

    .cia-learning-path-weeks {
        display: grid;
        gap: 0.75rem;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 22rem), 1fr));
        margin-top: 1rem;
    }

    .cia-learning-week summary {
        cursor: pointer;
        font-weight: 600;
    }

    .cia-learning-week-day {
        border-top: 1px solid #e5e7eb;
        padding-block: 0.5rem;
    }

    .cia-role-matching {
        margin-block: 2rem;
    }

    .cia-role-matching form {
        display: grid;
        gap: 0.75rem;
        max-width: 42rem;
    }

    .cia-role-matching input {
        padding: 0.6rem;
    }

    .cia-role-matching button {
        justify-self: start;
        padding: 0.65rem 1rem;
    }

    .cia-role-matches {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 18rem), 1fr));
        margin-top: 1rem;
    }

    .cia-role-match {
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        padding: 1rem;
    }

    .cia-role-match header {
        display: flex;
        justify-content: space-between;
    }
</style>

<script>
    (() => {
        const form = document.getElementById('cia-readiness-form');
        const dialog = document.getElementById('cia-readiness-dialog');
        const status = document.getElementById('cia-readiness-status');
        const fields = [
            ['project_completion_score', 'Project completion'],
            ['assessment_score', 'Assessment scores'],
            ['cia_usage_score', 'CIA usage'],
            ['docs_score', 'Documentation'],
            ['viva_score', 'Viva scores'],
            ['code_review_score', 'Code review'],
            ['demo_clarity_score', 'Demo clarity'],
            ['git_quality_score', 'Git quality'],
        ];
        const groups = [
            {
                prefix: 'placement',
                total: 'placement_readiness_score',
                metrics: fields.slice(0, 4),
            },
            {
                prefix: 'interview',
                total: 'interview_readiness_score',
                metrics: fields.slice(4),
            },
        ];
        let currentScores = {};

        const scoreValue = (value) => {
            const score = Number(value);
            return Number.isFinite(score) ? Math.min(100, Math.max(0, score)) : 0;
        };

        const renderScores = (scores) => {
            currentScores = scores || {};
            groups.forEach((group) => {
                const total = scoreValue(currentScores[group.total]);
                const ring = document.getElementById(`cia-${group.prefix}-score-ring`);
                ring.style.setProperty('--cia-readiness-score', `${total}%`);
                document.getElementById(`cia-${group.prefix}-score`).textContent = `${total.toFixed(2).replace(/\.00$/, '')}%`;

                const breakdown = document.getElementById(`cia-${group.prefix}-breakdown`);
                breakdown.replaceChildren();
                group.metrics.forEach(([key, label]) => {
                    const value = scoreValue(currentScores[key]);
                    const row = document.createElement('div');
                    row.className = 'cia-readiness-metric';
                    const name = document.createElement('span');
                    name.textContent = label;
                    const amount = document.createElement('span');
                    amount.textContent = `${value.toFixed(2).replace(/\.00$/, '')}%`;
                    const progress = document.createElement('progress');
                    progress.max = 100;
                    progress.value = value;
                    progress.className = value >= 80 ? 'is-high' : value >= 50 ? 'is-medium' : 'is-low';
                    progress.setAttribute('aria-label', `${label}: ${value} out of 100`);
                    row.append(name, amount, progress);
                    breakdown.appendChild(row);
                });
            });
        };

        document.getElementById('cia-readiness-edit').addEventListener('click', () => {
            fields.forEach(([key]) => {
                form.elements[key].value = currentScores[key] ?? 0;
            });
            dialog.showModal();
        });
        document.getElementById('cia-readiness-close').addEventListener('click', () => dialog.close());
        document.getElementById('cia-readiness-cancel').addEventListener('click', () => dialog.close());

        fetch('/candidate/cia/career/readiness-scores', {
            headers: { 'Accept': 'application/json' },
        })
            .then(async (response) => {
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.message || 'Readiness scores could not be loaded.');
                }
                renderScores(result.scores);
                status.textContent = result.scores ? 'Your latest readiness scores.' : 'No scores yet. Enter your metrics to calculate your readiness.';
            })
            .catch((error) => {
                status.textContent = error.message || 'Readiness scores could not be loaded.';
            });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            status.textContent = 'Calculating and saving your readiness scores...';
            const metrics = Object.fromEntries(fields.map(([key]) => [key, Number(form.elements[key].value)]));

            try {
                const response = await fetch('/candidate/cia/career/calculate-scores', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                    },
                    body: JSON.stringify(metrics),
                });
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.message || 'Readiness scores could not be calculated.');
                }
                renderScores(result.scores);
                status.textContent = result.message;
                dialog.close();
            } catch (error) {
                status.textContent = error.message || 'Readiness scores could not be calculated.';
            }
        });
    })();
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
<script>
    (() => {
        const form = document.getElementById('cia-skill-gap-form');
        const status = document.getElementById('cia-skill-gap-status');
        const score = document.getElementById('cia-skill-gap-score');
        const gaps = document.getElementById('cia-skill-gap-list');
        const canvas = document.getElementById('cia-skill-gap-radar');
        let radarChart = null;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            status.textContent = 'Analyzing your skill profile...';
            score.textContent = '';
            gaps.replaceChildren();

            try {
                const response = await fetch('/candidate/cia/career/skill-gap', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                    },
                    body: JSON.stringify({
                        target_role: form.elements.target_role.value,
                    }),
                });
                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Skill-gap analysis failed.');
                }

                score.textContent = `Readiness score: ${result.score}/100`;
                (result.gaps || []).forEach((gap) => {
                    const item = document.createElement('li');
                    item.textContent = gap;
                    gaps.appendChild(item);
                });

                if (!window.Chart) {
                    throw new Error('Chart.js did not load, so the radar chart cannot be displayed.');
                }

                if (radarChart) {
                    radarChart.destroy();
                }

                radarChart = new Chart(canvas, {
                    type: 'radar',
                    data: {
                        labels: result.radar_data.labels,
                        datasets: [
                            {
                                label: 'Candidate',
                                data: result.radar_data.candidate_scores,
                                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                borderColor: 'rgb(54, 162, 235)',
                            },
                            {
                                label: 'Target role',
                                data: result.radar_data.target_scores,
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                borderColor: 'rgb(255, 99, 132)',
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        scales: {
                            r: {
                                min: 0,
                                max: 100,
                                ticks: { stepSize: 20 },
                            },
                        },
                    },
                });
                status.textContent = 'Analysis complete.';
            } catch (error) {
                status.textContent = error.message;
            }
        });
    })();
</script>
<script>
    (() => {
        const form = document.getElementById('cia-learning-path-form');
        const status = document.getElementById('cia-learning-path-status');
        const chart = document.getElementById('cia-learning-path-chart');
        const results = document.getElementById('cia-learning-path-results');

        const splitList = (value) => value
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean);

        const appendText = (parent, tag, text) => {
            const element = document.createElement(tag);
            element.textContent = text;
            parent.appendChild(element);
            return element;
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            status.textContent = 'Building your 8-week learning path...';
            chart.replaceChildren();
            results.replaceChildren();

            try {
                const response = await fetch('/candidate/cia/career/learning-path', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                    },
                    body: JSON.stringify({
                        target_role: form.elements.target_role.value.trim(),
                        skills: splitList(form.elements.skills.value),
                        skill_gaps: splitList(form.elements.skill_gaps.value),
                        weekly_hours: Number(form.elements.weekly_hours.value),
                    }),
                });
                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Learning path generation failed.');
                }

                if (!Array.isArray(result.schedule) || result.schedule.length !== 8) {
                    throw new Error('Learning path service returned an invalid schedule.');
                }

                appendText(results, 'h3', result.title);
                appendText(results, 'p', result.summary);
                appendText(results, 'h4', 'Learning outcomes');
                const outcomes = document.createElement('ul');
                result.outcomes.forEach((outcome) => appendText(outcomes, 'li', outcome));
                results.appendChild(outcomes);

                result.schedule.forEach((week) => {
                    const card = document.createElement('details');
                    card.className = 'cia-learning-week';
                    const summary = appendText(card, 'summary', `Week ${week.week}: ${week.theme}`);
                    if (week.week === 1) {
                        summary.parentElement.open = true;
                    }

                    week.days.forEach((day) => {
                        const dayCard = document.createElement('article');
                        dayCard.className = 'cia-learning-week-day';
                        appendText(dayCard, 'h4', `Day ${day.day}: ${day.topic}`);
                        appendText(dayCard, 'p', day.practice);
                        appendText(dayCard, 'p', `${day.estimated_hours} study hours`);
                        card.appendChild(dayCard);
                    });
                    results.appendChild(card);
                });

                if (!window.mermaid) {
                    throw new Error('Mermaid did not load, so the timeline could not be displayed.');
                }
                window.mermaid.initialize({ startOnLoad: false, securityLevel: 'strict' });
                const rendered = await window.mermaid.render(
                    `cia-learning-path-${Date.now()}`,
                    result.mermaid
                );
                chart.innerHTML = rendered.svg;
                rendered.bindFunctions?.(chart);
                status.textContent = result.validation.approved
                    ? 'Your learning path is ready.'
                    : 'Your learning path is ready with review notes; check the schedule details.';
            } catch (error) {
                status.textContent = error.message;
            }
        });
    })();
</script>
<script>
    (() => {
        const form = document.getElementById('cia-role-matching-form');
        const status = document.getElementById('cia-role-matching-status');
        const results = document.getElementById('cia-role-matching-results');

        const appendText = (parent, tag, text) => {
            const element = document.createElement(tag);
            element.textContent = text;
            parent.appendChild(element);
            return element;
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            status.textContent = 'Matching your skills to internship and career roles...';
            results.replaceChildren();

            const skills = form.elements.skills.value
                .split(',')
                .map((skill) => skill.trim())
                .filter(Boolean);

            try {
                const response = await fetch('/candidate/cia/career/vector-search', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                    },
                    body: JSON.stringify({ skills }),
                });
                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Role matching could not be completed.');
                }

                result.matches.forEach((match, index) => {
                    const card = document.createElement('article');
                    card.className = 'cia-role-match';
                    const header = document.createElement('header');
                    appendText(header, 'span', `Rank #${index + 1}`);
                    appendText(header, 'strong', `${match.match_percentage}% match`);
                    card.appendChild(header);
                    appendText(card, 'h3', match.role);
                    appendText(card, 'p', match.match_criteria);

                    const metadata = Object.entries(match.metadata || {})
                        .map(([key, value]) => `${key.replaceAll('_', ' ')}: ${value}`)
                        .join(' · ');
                    if (metadata) {
                        appendText(card, 'p', metadata);
                    }

                    appendText(card, 'h4', 'Missing skills');
                    const gaps = document.createElement('ul');
                    if (match.missing_skills.length) {
                        match.missing_skills.forEach((skill) => appendText(gaps, 'li', skill));
                    } else {
                        appendText(gaps, 'li', 'No required skill gaps identified.');
                    }
                    card.appendChild(gaps);
                    results.appendChild(card);
                });
                status.textContent = 'Top role matches are ready.';
            } catch (error) {
                status.textContent = error.message;
            }
        });
    })();
</script>
<script>
    (() => {
        const form = document.getElementById('cia-career-recommendations-form');
        const status = document.getElementById('cia-career-recommendations-status');
        const results = document.getElementById('cia-career-recommendations-results');

        const splitList = (value) => value
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean);

        const appendText = (parent, tag, text) => {
            const element = document.createElement(tag);
            element.textContent = text;
            parent.appendChild(element);
            return element;
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            status.textContent = 'Building your career recommendations...';
            results.replaceChildren();

            try {
                const response = await fetch('/candidate/cia/career/recommendations', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                    },
                    body: JSON.stringify({
                        target_role: form.elements.target_role.value.trim(),
                        skills: splitList(form.elements.skills.value),
                        skill_gaps: splitList(form.elements.skill_gaps.value),
                    }),
                });
                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Career recommendations could not be generated.');
                }

                result.career_paths.forEach((path) => {
                    const card = document.createElement('article');
                    card.className = 'cia-career-path';
                    appendText(card, 'h3', path.title);
                    appendText(card, 'p', path.overview);
                    appendText(card, 'h4', 'Required competencies');
                    const competencies = document.createElement('ul');
                    path.required_competencies.forEach((competency) => {
                        appendText(competencies, 'li', competency);
                    });
                    card.appendChild(competencies);
                    appendText(card, 'h4', `Estimated timeframe: ${path.estimated_timeframe}`);
                    appendText(card, 'h4', 'Milestones');
                    const milestones = document.createElement('ol');
                    path.milestones.forEach((milestone) => {
                        appendText(
                            milestones,
                            'li',
                            `${milestone.name} — ${milestone.action} (${milestone.timeframe})`
                        );
                    });
                    card.appendChild(milestones);
                    results.appendChild(card);
                });
                status.textContent = 'Three career paths are ready.';
            } catch (error) {
                status.textContent = error.message;
            }
        });
    })();
</script>
