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

<section class="cia-resume-intelligence" aria-labelledby="cia-resume-intelligence-title">
    <h2 id="cia-resume-intelligence-title">Resume intelligence</h2>
    <form id="cia-ats-score-form" enctype="multipart/form-data">
        @csrf
        <label for="cia-ats-resume">Resume (PDF, max 10 MB)</label>
        <input id="cia-ats-resume" name="resume" type="file" accept=".pdf,application/pdf" required>

        <label for="cia-ats-job-description">Target job description</label>
        <textarea
            id="cia-ats-job-description"
            name="job_description"
            rows="8"
            maxlength="12000"
            required
        ></textarea>

        <button type="submit">Score resume</button>
    </form>
    <p id="cia-ats-score-status" role="status" aria-live="polite"></p>
    <div id="cia-ats-score-results" class="cia-ats-score-results" aria-live="polite"></div>
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
    .cia-resume-intelligence {
        margin-block: 2rem;
    }

    .cia-resume-intelligence form {
        display: grid;
        gap: 0.75rem;
        max-width: 42rem;
    }

    .cia-resume-intelligence input,
    .cia-resume-intelligence textarea {
        padding: 0.6rem;
    }

    .cia-resume-intelligence button {
        justify-self: start;
        padding: 0.65rem 1rem;
    }

    .cia-ats-score-card {
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        margin-top: 1rem;
        max-width: 48rem;
        padding: 1rem;
    }

    .cia-ats-total-score {
        display: inline-block;
        border-radius: 9999px;
        background: #e0f2fe;
        font-size: 1.25rem;
        font-weight: 700;
        padding: 0.4rem 0.8rem;
    }

    .cia-ats-category {
        display: grid;
        gap: 0.5rem;
        grid-template-columns: minmax(7rem, 1fr) 3fr 3rem;
        align-items: center;
        margin-block: 0.5rem;
    }

    .cia-ats-category progress {
        width: 100%;
    }

    .cia-ats-suggestion {
        border-top: 1px solid #e5e7eb;
        padding-block: 0.75rem;
    }

    .cia-ats-suggestion summary {
        cursor: pointer;
        font-weight: 600;
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
        const form = document.getElementById('cia-ats-score-form');
        const status = document.getElementById('cia-ats-score-status');
        const results = document.getElementById('cia-ats-score-results');
        const categories = [
            ['keywords', 'Keywords'],
            ['formatting', 'Formatting'],
            ['experience', 'Experience'],
            ['skills', 'Skills'],
        ];

        const appendText = (parent, tag, text) => {
            const element = document.createElement(tag);
            element.textContent = text;
            parent.appendChild(element);
            return element;
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            status.textContent = 'Extracting and scoring your resume...';
            results.replaceChildren();

            try {
                const response = await fetch('/candidate/cia/career/ats-score', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                    },
                    body: new FormData(form),
                });
                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Resume scoring failed.');
                }

                const card = document.createElement('article');
                card.className = 'cia-ats-score-card';
                appendText(card, 'h3', 'ATS match score');
                const totalScore = appendText(card, 'p', `${result.total_score}/100`);
                totalScore.className = 'cia-ats-total-score';

                appendText(card, 'h4', 'Category scores');
                categories.forEach(([key, label]) => {
                    const row = document.createElement('div');
                    row.className = 'cia-ats-category';
                    appendText(row, 'span', label);
                    const progress = document.createElement('progress');
                    progress.max = 100;
                    progress.value = result[key];
                    progress.setAttribute('aria-label', `${label}: ${result[key]} out of 100`);
                    row.appendChild(progress);
                    appendText(row, 'span', `${result[key]}/100`);
                    card.appendChild(row);
                });

                appendText(card, 'h4', 'Keyword gaps');
                const keywordGaps = document.createElement('ul');
                if (result.keyword_gaps.length === 0) {
                    appendText(keywordGaps, 'li', 'No major keyword gaps identified.');
                } else {
                    result.keyword_gaps.forEach((gap) => appendText(keywordGaps, 'li', gap));
                }
                card.appendChild(keywordGaps);

                appendText(card, 'h4', 'Rewrite suggestions');
                if (result.rewrite_suggestions.length === 0) {
                    appendText(card, 'p', 'No section-specific rewrite suggestions.');
                } else {
                    result.rewrite_suggestions.forEach((suggestion) => {
                        const item = document.createElement('details');
                        item.className = 'cia-ats-suggestion';
                        appendText(item, 'summary', suggestion.section);
                        appendText(item, 'p', `Opportunity: ${suggestion.current_issue}`);
                        appendText(item, 'p', `Suggested rewrite: ${suggestion.suggestion}`);
                        card.appendChild(item);
                    });
                }

                results.appendChild(card);
                status.textContent = 'Resume analysis complete.';
            } catch (error) {
                status.textContent = error.message || 'Resume scoring failed.';
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
