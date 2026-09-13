<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  [key: string]: unknown
  totalFocusMinutes?: number
  focusedMinutes?: number
  currentSessionSeconds?: number
  sessionSeconds?: number
  activeSessionElapsedSeconds?: number
  currentSessionMinutes?: number
  sessionElapsedMinutes?: number
  maxGrowthMinutes?: number
  idealSessionMinutes?: number | null
  recommendedSessionMinutes?: number | null
  feedbackCount?: number
}

const props = defineProps<Props>()

const stageLabels = [
  'Pousse',
  'Tige',
  'Jeunes feuilles',
  'Petite plante',
  'Plante stable',
  'Arbuste naissant',
  'Arbuste',
  'Petit arbre',
  'Arbre jeune',
  'Arbre fort',
  'Grand arbre',
  'Arbre complet',
] as const

function asNumber(value: unknown): number | null {
  return typeof value === 'number'
    && Number.isFinite(value)
    ? value
    : null
}

function clamp(
  value: number,
  min: number,
  max: number,
): number {
  return Math.min(
    max,
    Math.max(min, value),
  )
}

function formatDuration(
  minutes: number,
): string {
  const safeMinutes =
    Math.max(
      0,
      Math.round(minutes),
    )

  const hours =
    Math.floor(
      safeMinutes / 60,
    )

  const remainder =
    safeMinutes % 60

  if (
    hours > 0
    && remainder > 0
  ) {
    return `${hours}h ${remainder}m`
  }

  if (hours > 0) {
    return `${hours}h`
  }

  return `${remainder}m`
}

const maxGrowthMinutes = computed(
  () =>
    Math.max(
      5,
      Math.round(
        asNumber(
          props.maxGrowthMinutes,
        ) ?? 60,
      ),
    ),
)

const totalFocusMinutes = computed(
  () =>
    Math.max(
      0,
      Math.round(
        asNumber(
          props.totalFocusMinutes,
        )
          ?? asNumber(
            props.focusedMinutes,
          )
          ?? 0,
      ),
    ),
)

const elapsedMinutes = computed(() => {
  const seconds =
    asNumber(
      props.currentSessionSeconds,
    )
    ?? asNumber(
      props.sessionSeconds,
    )
    ?? asNumber(
      props.activeSessionElapsedSeconds,
    )

  if (seconds !== null) {
    return clamp(
      Math.floor(
        seconds / 60,
      ),
      0,
      maxGrowthMinutes.value,
    )
  }

  const minutes =
    asNumber(
      props.currentSessionMinutes,
    )
    ?? asNumber(
      props.sessionElapsedMinutes,
    )
    ?? 0

  return clamp(
    Math.floor(minutes),
    0,
    maxGrowthMinutes.value,
  )
})

/*
 * Une vraie progression par étapes :
 * - on part d'une petite pousse,
 * - toutes les 5 minutes ça grandit,
 * - la dernière minute reste au max.
 */
const visualStage = computed(() => {
  const maximum =
    maxGrowthMinutes.value

  if (
    elapsedMinutes.value
    >= maximum - 1
  ) {
    return 12
  }

  const preMaximumMinutes =
    Math.max(
      1,
      maximum - 1,
    )

  const totalGrowthSteps =
    Math.max(
      1,
      Math.ceil(
        preMaximumMinutes / 5,
      ),
    )

  const currentGrowthStep =
    Math.floor(
      elapsedMinutes.value / 5,
    )

  return clamp(
    1
    + Math.round(
      (
        currentGrowthStep
        / totalGrowthSteps
      ) * 10,
    ),
    1,
    11,
  )
})

const currentStageLabel = computed(
  () =>
    stageLabels[
      visualStage.value - 1
    ],
)

const sessionPercent = computed(
  () => {
    if (
      elapsedMinutes.value
      >= maxGrowthMinutes.value - 1
    ) {
      return 100
    }

    return clamp(
      (
        elapsedMinutes.value
        / maxGrowthMinutes.value
      ) * 100,
      0,
      100,
    )
  },
)

const nextStepLabel = computed(() => {
  if (visualStage.value >= 12) {
    return 'Taille maximale atteinte'
  }

  const nextFiveMinuteStep =
    (
      Math.floor(
        elapsedMinutes.value / 5,
      ) + 1
    ) * 5

  const maximumStep =
    maxGrowthMinutes.value - 1

  const nextStep =
    Math.min(
      nextFiveMinuteStep,
      maximumStep,
    )

  const remaining =
    Math.max(
      1,
      nextStep
      - elapsedMinutes.value,
    )

  return `Prochaine pousse dans ${remaining} min`
})

const globalLevel = computed(() => {
  const minutes =
    totalFocusMinutes.value

  if (minutes >= 240) {
    return 'Forêt'
  }

  if (minutes >= 180) {
    return 'Grand arbre'
  }

  if (minutes >= 120) {
    return 'Arbre'
  }

  if (minutes >= 60) {
    return 'Arbuste'
  }

  return 'Jeune pousse'
})

const idealSessionLabel = computed(() => {
  const ideal =
    asNumber(
      props.idealSessionMinutes,
    )
    ?? asNumber(
      props.recommendedSessionMinutes,
    )

  if (
    ideal === null
    || ideal <= 0
  ) {
    return 'En apprentissage'
  }

  return `${Math.round(ideal)} min`
})

const feedbackCount = computed(
  () =>
    Math.max(
      0,
      Math.round(
        asNumber(
          props.feedbackCount,
        ) ?? 0,
      ),
    ),
)

const stemHeight = computed(
  () =>
    24 + (
      sessionPercent.value
      / 100
    ) * 86,
)

const trunkHeight = computed(
  () =>
    14 + (
      sessionPercent.value
      / 100
    ) * 94,
)

const canopyRadius = computed(
  () =>
    10 + (
      sessionPercent.value
      / 100
    ) * 44,
)

const canopyCenterY = computed(
  () =>
    182
    - trunkHeight.value
    - canopyRadius.value
    * 0.55,
)

function stageVisible(
  stage: number,
): number {
  return visualStage.value >= stage
    ? 1
    : 0.08
}

function stageScale(
  stage: number,
  collapsed = 0.3,
): string {
  return `scale(${
    visualStage.value >= stage
      ? 1
      : collapsed
  })`
}
</script>

<template>
  <section class="garden-card">
    <header class="garden-header">
      <div>
        <p class="garden-eyebrow">
          Jardin Pomodoro
        </p>

        <h3 class="garden-title">
          {{ currentStageLabel }}
        </h3>

        <p class="garden-subtitle">
          La pousse grandit toutes les
          5 minutes pendant votre
          session.
        </p>
      </div>

      <div class="garden-summary">
        <strong>
          {{
            formatDuration(
              totalFocusMinutes,
            )
          }}
        </strong>

        <span>
          concentration cumulée
        </span>
      </div>
    </header>

    <div class="garden-layout">
      <div class="garden-scene">
        <svg
          viewBox="0 0 320 220"
          aria-label="Croissance de la pousse Pomodoro"
          role="img"
        >
          <defs>
            <linearGradient
              id="gardenSky"
              x1="0%"
              x2="0%"
              y1="0%"
              y2="100%"
            >
              <stop
                offset="0%"
                stop-color="#eef6ff"
              />

              <stop
                offset="100%"
                stop-color="#ffffff"
              />
            </linearGradient>

            <linearGradient
              id="gardenTrunk"
              x1="0%"
              x2="100%"
              y1="0%"
              y2="100%"
            >
              <stop
                offset="0%"
                stop-color="#8d5b32"
              />

              <stop
                offset="100%"
                stop-color="#5a3820"
              />
            </linearGradient>

            <linearGradient
              id="gardenLeaf"
              x1="0%"
              x2="100%"
              y1="0%"
              y2="100%"
            >
              <stop
                offset="0%"
                stop-color="#8bd56d"
              />

              <stop
                offset="100%"
                stop-color="#34a853"
              />
            </linearGradient>
          </defs>

          <rect
            x="10"
            y="10"
            width="300"
            height="200"
            rx="28"
            fill="url(#gardenSky)"
          />

          <circle
            cx="262"
            cy="52"
            r="20"
            fill="#fbbc04"
            :style="{
              opacity: String(
                0.45
                + sessionPercent / 180,
              ),
            }"
          />

          <ellipse
            cx="160"
            cy="182"
            rx="112"
            ry="21"
            fill="#d8ebd7"
          />

          <ellipse
            cx="160"
            cy="188"
            rx="86"
            ry="13"
            fill="#b6d8a4"
          />

          <g
            :style="{
              opacity: String(
                stageVisible(1),
              ),
            }"
          >
            <path
              d="M160 181 C154 185 149 191 145 198"
              fill="none"
              stroke="#8d5b32"
              stroke-linecap="round"
              stroke-width="4"
            />

            <path
              d="M160 181 C166 186 171 192 175 198"
              fill="none"
              stroke="#8d5b32"
              stroke-linecap="round"
              stroke-width="4"
            />
          </g>

          <rect
            x="154"
            :y="182 - stemHeight"
            width="12"
            :height="stemHeight"
            rx="6"
            fill="#34a853"
          />

          <g
            :style="{
              opacity: String(
                stageVisible(1),
              ),
              transform: stageScale(1),
              transformOrigin: '160px 170px',
            }"
          >
            <ellipse
              cx="146"
              cy="166"
              rx="14"
              ry="8"
              fill="#60c96c"
              transform="rotate(-26 146 166)"
            />

            <ellipse
              cx="174"
              cy="166"
              rx="14"
              ry="8"
              fill="#60c96c"
              transform="rotate(26 174 166)"
            />
          </g>

          <g
            :style="{
              opacity: String(
                stageVisible(3),
              ),
              transform: stageScale(3),
              transformOrigin: '160px 152px',
            }"
          >
            <ellipse
              cx="142"
              cy="148"
              rx="16"
              ry="9"
              fill="#55bd63"
              transform="rotate(-32 142 148)"
            />

            <ellipse
              cx="178"
              cy="148"
              rx="16"
              ry="9"
              fill="#55bd63"
              transform="rotate(32 178 148)"
            />
          </g>

          <g
            :style="{
              opacity: String(
                stageVisible(5),
              ),
            }"
          >
            <rect
              x="150"
              :y="182 - trunkHeight"
              width="20"
              :height="trunkHeight"
              rx="9"
              fill="url(#gardenTrunk)"
            />
          </g>

          <g
            :style="{
              opacity: String(
                stageVisible(6),
              ),
            }"
          >
            <path
              d="M160 120 C142 116 134 108 126 97"
              fill="none"
              stroke="#6b4424"
              stroke-linecap="round"
              stroke-width="6"
            />

            <path
              d="M160 118 C178 114 186 106 194 95"
              fill="none"
              stroke="#6b4424"
              stroke-linecap="round"
              stroke-width="6"
            />
          </g>

          <g
            :style="{
              opacity: String(
                stageVisible(7),
              ),
              transform: `translateY(${
                18
                - sessionPercent / 8
              }px)`,
            }"
          >
            <circle
              cx="160"
              :cy="canopyCenterY"
              :r="canopyRadius"
              fill="url(#gardenLeaf)"
            />

            <circle
              cx="132"
              :cy="canopyCenterY + 8"
              :r="canopyRadius * 0.72"
              fill="#5cc26a"
            />

            <circle
              cx="188"
              :cy="canopyCenterY + 8"
              :r="canopyRadius * 0.72"
              fill="#5cc26a"
            />
          </g>

          <g
            :style="{
              opacity: String(
                stageVisible(9),
              ),
              transform: stageScale(9, 0.45),
              transformOrigin: '160px 104px',
            }"
          >
            <circle
              cx="116"
              cy="118"
              r="22"
              fill="#7fd665"
            />

            <circle
              cx="204"
              cy="118"
              r="22"
              fill="#7fd665"
            />

            <circle
              cx="160"
              cy="96"
              r="26"
              fill="#79cf5f"
            />
          </g>

          <g
            :style="{
              opacity: String(
                stageVisible(11),
              ),
              transform: stageScale(11, 0.5),
              transformOrigin: '160px 98px',
            }"
          >
            <circle
              cx="98"
              cy="132"
              r="16"
              fill="#8add6d"
            />

            <circle
              cx="222"
              cy="132"
              r="16"
              fill="#8add6d"
            />

            <circle
              cx="136"
              cy="84"
              r="18"
              fill="#8add6d"
            />

            <circle
              cx="184"
              cy="84"
              r="18"
              fill="#8add6d"
            />
          </g>

          <g
            :style="{
              opacity: String(
                stageVisible(12),
              ),
              transform: stageScale(12, 0.6),
              transformOrigin: '160px 105px',
            }"
          >
            <circle
              cx="124"
              cy="110"
              r="4"
              fill="#4285f4"
            />

            <circle
              cx="198"
              cy="104"
              r="4"
              fill="#ea4335"
            />

            <circle
              cx="158"
              cy="82"
              r="4"
              fill="#fbbc04"
            />

            <circle
              cx="178"
              cy="126"
              r="4"
              fill="#4285f4"
            />

            <circle
              cx="142"
              cy="126"
              r="4"
              fill="#34a853"
            />
          </g>
        </svg>
      </div>

      <div class="garden-metrics">
        <article class="garden-metric">
          <span>Session en cours</span>

          <strong>
            {{
              formatDuration(
                elapsedMinutes,
              )
            }}
          </strong>
        </article>

        <article class="garden-metric">
          <span>Étape actuelle</span>

          <strong>
            {{
              visualStage
            }}/12 ·
            {{
              currentStageLabel
            }}
          </strong>
        </article>

        <article class="garden-metric">
          <span>Niveau global</span>

          <strong>
            {{ globalLevel }}
          </strong>
        </article>

        <article class="garden-metric">
          <span>Durée idéale estimée</span>

          <strong>
            {{
              idealSessionLabel
            }}
          </strong>

          <small>
            {{
              feedbackCount > 0
                ? `${feedbackCount} retour${feedbackCount > 1 ? 's' : ''} enregistré${feedbackCount > 1 ? 's' : ''}`
                : 'Ajoutez vos ressentis en fin de session'
            }}
          </small>
        </article>
      </div>
    </div>

    <div class="garden-progress-block">
      <div class="garden-progress-bar">
        <span
          :style="{
            width: `${sessionPercent}%`,
          }"
        />
      </div>

      <div class="garden-progress-meta">
        <span>
          {{ nextStepLabel }}
        </span>

        <span>
          {{ elapsedMinutes }}/{{
            maxGrowthMinutes
          }} min
        </span>
      </div>
    </div>

    <ol class="garden-stage-grid">
      <li
        v-for="(
          label,
          index
        ) in stageLabels"
        :key="label"
        :class="{
          active:
            index + 1
            <= visualStage,
        }"
      >
        <span>
          {{
            index + 1
          }}
        </span>

        <small>
          {{ label }}
        </small>
      </li>
    </ol>
  </section>
</template>

<style scoped>
.garden-card {
  display: grid;
  gap: 1.5rem;
  padding: 1.5rem;
  border: 1px solid #d7e2f7;
  border-radius: 28px;
  background:
    linear-gradient(
      180deg,
      #ffffff 0%,
      #f7fbff 100%
    );
  box-shadow:
    0 18px 40px rgba(66, 133, 244, 0.08);
}

.garden-header {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
}

.garden-eyebrow {
  margin: 0 0 0.35rem;
  color: #137333;
  font-size: 0.82rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.garden-title {
  margin: 0;
  color: #1f1f1f;
  font-size: 2rem;
  line-height: 1.1;
}

.garden-subtitle {
  margin: 0.45rem 0 0;
  color: #5f6368;
  line-height: 1.55;
}

.garden-summary {
  display: grid;
  gap: 0.15rem;
  min-width: 180px;
  padding: 0.9rem 1rem;
  border-radius: 20px;
  background: #eef4ff;
  color: #174ea6;
  text-align: right;
}

.garden-summary strong {
  font-size: 1.4rem;
  line-height: 1;
}

.garden-summary span {
  font-size: 0.92rem;
}

.garden-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.8fr);
  gap: 1.25rem;
  align-items: stretch;
}

.garden-scene {
  padding: 0.75rem;
  border-radius: 24px;
  background:
    linear-gradient(
      180deg,
      #f5faff 0%,
      #ffffff 100%
    );
  border: 1px solid #dbe7f8;
}

.garden-scene svg {
  width: 100%;
  height: auto;
  display: block;
}

.garden-scene svg * {
  transition:
    opacity 220ms ease,
    transform 320ms ease,
    height 320ms ease,
    y 320ms ease,
    cy 320ms ease,
    r 320ms ease;
}

.garden-metrics {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.garden-metric {
  display: grid;
  gap: 0.35rem;
  padding: 1rem;
  border: 1px solid #dde6f6;
  border-radius: 22px;
  background: #ffffff;
}

.garden-metric span {
  color: #5f6368;
  font-size: 0.92rem;
}

.garden-metric strong {
  color: #1f1f1f;
  font-size: 1.2rem;
  line-height: 1.2;
}

.garden-metric small {
  color: #6f7680;
  line-height: 1.45;
}

.garden-progress-block {
  display: grid;
  gap: 0.6rem;
}

.garden-progress-bar {
  overflow: hidden;
  height: 14px;
  border-radius: 999px;
  background: #e6eefb;
}

.garden-progress-bar span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background:
    linear-gradient(
      90deg,
      #34a853 0%,
      #8ad76b 100%
    );
  transition: width 320ms ease;
}

.garden-progress-meta {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  color: #5f6368;
  font-size: 0.95rem;
}

.garden-stage-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 0.75rem;
  padding: 0;
  margin: 0;
  list-style: none;
}

.garden-stage-grid li {
  display: grid;
  gap: 0.35rem;
  justify-items: start;
  padding: 0.9rem;
  border: 1px solid #e3e9f5;
  border-radius: 18px;
  background: #ffffff;
  color: #5f6368;
}

.garden-stage-grid li.active {
  border-color: #c7dafc;
  background: #eef5ff;
  color: #174ea6;
}

.garden-stage-grid span {
  display: inline-flex;
  width: 1.9rem;
  height: 1.9rem;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  background: #edf1f8;
  color: inherit;
  font-size: 0.85rem;
  font-weight: 700;
}

.garden-stage-grid li.active span {
  background: #4285f4;
  color: #ffffff;
}

.garden-stage-grid small {
  line-height: 1.35;
}

@media (max-width: 980px) {
  .garden-layout {
    grid-template-columns: 1fr;
  }

  .garden-metrics {
    grid-template-columns: 1fr 1fr;
  }

  .garden-stage-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

@media (max-width: 720px) {
  .garden-card {
    padding: 1.1rem;
    border-radius: 22px;
  }

  .garden-header {
    flex-direction: column;
  }

  .garden-summary {
    min-width: 0;
    width: 100%;
    text-align: left;
  }

  .garden-title {
    font-size: 1.65rem;
  }

  .garden-metrics {
    grid-template-columns: 1fr;
  }

  .garden-progress-meta {
    flex-direction: column;
    align-items: flex-start;
  }

  .garden-stage-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
