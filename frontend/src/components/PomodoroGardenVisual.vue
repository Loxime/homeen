<script setup lang="ts">
import {
  computed,
} from 'vue'

interface Props {
  totalFocusMinutes?: number
  currentSessionSeconds?: number
  maxGrowthMinutes?: number
  recommendedSessionMinutes?: number | null
  feedbackCount?: number
}

const props =
  withDefaults(
    defineProps<Props>(),
    {
      totalFocusMinutes: 0,
      currentSessionSeconds: 0,
      maxGrowthMinutes: 25,
      recommendedSessionMinutes: null,
      feedbackCount: 0,
    },
  )

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

function clamp(
  value: number,
  minimum: number,
  maximum: number,
): number {
  return Math.min(
    maximum,
    Math.max(
      minimum,
      value,
    ),
  )
}

const durationMinutes =
  computed(
    () =>
      Math.max(
        5,
        Math.round(
          props.maxGrowthMinutes,
        ),
      ),
  )

const elapsedMinutes =
  computed(
    () =>
      clamp(
        Math.floor(
          props.currentSessionSeconds
          / 60,
        ),
        0,
        durationMinutes.value,
      ),
  )

const visualStage =
  computed(() => {
    const duration =
      durationMinutes.value

    /*
     * La dernière minute est toujours
     * passée au sprite maximum.
     */
    if (
      elapsedMinutes.value
      >= duration - 1
    ) {
      return 12
    }

    /*
     * L'arbre ne change que toutes
     * les cinq minutes.
     */
    const fiveMinuteStep =
      Math.floor(
        elapsedMinutes.value / 5,
      )

    const availableSteps =
      Math.max(
        1,
        Math.ceil(
          (duration - 1) / 5,
        ),
      )

    /*
     * On répartit les 11 étapes
     * intermédiaires sur la durée
     * réelle de la session.
     *
     * Exemple 25 min :
     * 0  -> étape 1
     * 5  -> étape 3/4
     * 10 -> étape 6
     * 15 -> étape 8/9
     * 20 -> étape 11
     * 24 -> étape 12
     */
    return clamp(
      1
      + Math.round(
        (
          fiveMinuteStep
          / availableSteps
        ) * 10,
      ),
      1,
      11,
    )
  })

const currentStageLabel =
  computed(
    () =>
      stageLabels[
        visualStage.value - 1
      ],
  )

const spriteSource =
  computed(
    () =>
      `/pomodoro-garden/stage-${
        String(
          visualStage.value,
        ).padStart(
          2,
          '0',
        )
      }.svg`,
  )

const progressPercent =
  computed(() => {
    if (
      elapsedMinutes.value
      >= durationMinutes.value - 1
    ) {
      return 100
    }

    return clamp(
      (
        elapsedMinutes.value
        / durationMinutes.value
      ) * 100,
      0,
      100,
    )
  })

const nextGrowth =
  computed(() => {
    if (
      visualStage.value === 12
    ) {
      return null
    }

    const nextStep =
      Math.min(
        (
          Math.floor(
            elapsedMinutes.value / 5,
          ) + 1
        ) * 5,
        durationMinutes.value - 1,
      )

    return Math.max(
      1,
      nextStep
      - elapsedMinutes.value,
    )
  })

const globalLevel =
  computed(() => {
    const minutes =
      props.totalFocusMinutes

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

function formatMinutes(
  minutes: number,
): string {
  const rounded =
    Math.max(
      0,
      Math.round(minutes),
    )

  const hours =
    Math.floor(
      rounded / 60,
    )

  const remainder =
    rounded % 60

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
</script>

<template>
  <section class="pixel-garden">
    <header class="pixel-garden-header">
      <div>
        <span class="pixel-garden-kicker">
          Jardin Pomodoro
        </span>

        <h2>
          {{ currentStageLabel }}
        </h2>

        <p>
          Votre arbre évolue toutes les
          5 minutes de concentration.
        </p>
      </div>

      <div class="pixel-garden-total">
        <strong>
          {{
            formatMinutes(
              totalFocusMinutes,
            )
          }}
        </strong>

        <span>
          concentration cumulée
        </span>
      </div>
    </header>

    <div class="pixel-garden-body">
      <div class="pixel-scene">
        <img
          :key="spriteSource"
          :src="spriteSource"
          :alt="
            `Étape ${visualStage} : ${currentStageLabel}`
          "
          class="pixel-tree"
        />

        <div class="pixel-stage-badge">
          Étape
          {{ visualStage }}/12
        </div>
      </div>

      <div class="pixel-stats">
        <article>
          <span>
            Session
          </span>

          <strong>
            {{
              elapsedMinutes
            }}
            /
            {{
              durationMinutes
            }}
            min
          </strong>
        </article>

        <article>
          <span>
            Niveau global
          </span>

          <strong>
            {{ globalLevel }}
          </strong>
        </article>

        <article>
          <span>
            Prochaine évolution
          </span>

          <strong>
            <template
              v-if="
                nextGrowth !== null
              "
            >
              {{ nextGrowth }} min
            </template>

            <template v-else>
              Maximum
            </template>
          </strong>
        </article>

        <article>
          <span>
            Durée idéale
          </span>

          <strong>
            <template
              v-if="
                recommendedSessionMinutes
                !== null
              "
            >
              {{
                recommendedSessionMinutes
              }}
              min
            </template>

            <template v-else>
              En apprentissage
            </template>
          </strong>

          <small>
            {{
              feedbackCount
            }}
            retour{{
              feedbackCount > 1
                ? 's'
                : ''
            }}
          </small>
        </article>
      </div>
    </div>

    <div class="pixel-progress">
      <div class="pixel-progress-track">
        <span
          :style="{
            width:
              `${progressPercent}%`,
          }"
        />
      </div>

      <div class="pixel-progress-labels">
        <span>
          <template
            v-if="
              nextGrowth !== null
            "
          >
            Prochaine pousse dans
            {{ nextGrowth }} min
          </template>

          <template v-else>
            Taille maximale atteinte
          </template>
        </span>

        <span>
          {{
            Math.round(
              progressPercent,
            )
          }}%
        </span>
      </div>
    </div>

    <div class="pixel-timeline">
      <div
        v-for="
          (label, index)
          in stageLabels
        "
        :key="label"
        class="pixel-timeline-step"
        :class="{
          done:
            index + 1
            < visualStage,
          current:
            index + 1
            === visualStage,
        }"
      >
        <span>
          {{ index + 1 }}
        </span>

        <small>
          {{ label }}
        </small>
      </div>
    </div>
  </section>
</template>

<style scoped>
.pixel-garden {
  display: grid;
  gap: 22px;
  width: 100%;
  padding: clamp(18px, 3vw, 28px);
  border: 1px solid #dfe3e7;
  border-radius: 24px;
  background: #fff;
}

.pixel-garden-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
}

.pixel-garden-header h2 {
  margin: 4px 0 6px;
  font-size: clamp(1.7rem, 4vw, 2.2rem);
}

.pixel-garden-header p {
  margin: 0;
  color: #5f6368;
}

.pixel-garden-kicker {
  color: #188038;
  font-size: .78rem;
  font-weight: 800;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.pixel-garden-total {
  flex: 0 0 auto;
  min-width: 170px;
  padding: 14px 18px;
  display: grid;
  gap: 3px;
  border-radius: 18px;
  background: #edf4ff;
  color: #174ea6;
  text-align: right;
}

.pixel-garden-total strong {
  font-size: 1.35rem;
}

.pixel-garden-total span {
  font-size: .82rem;
}

.pixel-garden-body {
  display: grid;
  grid-template-columns:
    minmax(280px, 1.35fr)
    minmax(250px, .65fr);
  gap: 20px;
}

.pixel-scene {
  position: relative;
  min-width: 0;
  overflow: hidden;
  border: 1px solid #d9e3f3;
  border-radius: 20px;
  background: #eef4fb;
}

.pixel-tree {
  display: block;
  width: 100%;
  height: 100%;
  min-height: 300px;
  max-height: 440px;
  object-fit: contain;

  /*
   * Important :
   * aucun lissage des pixels.
   */
  image-rendering: pixelated;
  image-rendering: crisp-edges;

  animation:
    pixel-grow-in
    360ms
    steps(4, end);
}

@keyframes pixel-grow-in {
  from {
    opacity: .35;
    transform:
      translateY(8px)
      scale(.94);
  }

  to {
    opacity: 1;
    transform:
      translateY(0)
      scale(1);
  }
}

.pixel-stage-badge {
  position: absolute;
  right: 14px;
  bottom: 14px;
  padding: 7px 11px;
  border: 1px solid
    rgba(255,255,255,.65);
  border-radius: 999px;
  background:
    rgba(31,31,31,.78);
  color: #fff;
  font-size: .78rem;
  font-weight: 700;
}

.pixel-stats {
  display: grid;
  grid-template-columns:
    repeat(
      2,
      minmax(0,1fr)
    );
  gap: 12px;
}

.pixel-stats article {
  min-width: 0;
  padding: 16px;
  display: grid;
  align-content: start;
  gap: 7px;
  border: 1px solid #e0e3e7;
  border-radius: 18px;
  background: #fff;
}

.pixel-stats span {
  color: #5f6368;
  font-size: .82rem;
}

.pixel-stats strong {
  font-size: 1.08rem;
}

.pixel-stats small {
  color: #747775;
}

.pixel-progress {
  display: grid;
  gap: 8px;
}

.pixel-progress-track {
  height: 12px;
  overflow: hidden;
  border-radius: 999px;
  background: #e4ece6;
}

.pixel-progress-track span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #34a853;
  transition:
    width 250ms ease;
}

.pixel-progress-labels {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  color: #5f6368;
  font-size: .85rem;
}

.pixel-timeline {
  display: grid;
  grid-template-columns:
    repeat(
      6,
      minmax(0,1fr)
    );
  gap: 8px;
}

.pixel-timeline-step {
  min-width: 0;
  padding: 10px;
  display: grid;
  gap: 6px;
  border: 1px solid #e0e3e7;
  border-radius: 14px;
  color: #747775;
}

.pixel-timeline-step > span {
  width: 28px;
  height: 28px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: #eef1f5;
  font-size: .76rem;
  font-weight: 800;
}

.pixel-timeline-step small {
  overflow: hidden;
  text-overflow: ellipsis;
}

.pixel-timeline-step.done {
  border-color: #c8e6cf;
  background: #f2faf4;
  color: #137333;
}

.pixel-timeline-step.done > span {
  background: #ceead6;
}

.pixel-timeline-step.current {
  border-color: #a8c7fa;
  background: #edf4ff;
  color: #174ea6;
}

.pixel-timeline-step.current > span {
  background: #1a73e8;
  color: #fff;
}

@media (max-width: 900px) {
  .pixel-garden-body {
    grid-template-columns: 1fr;
  }

  .pixel-tree {
    min-height: 260px;
  }

  .pixel-timeline {
    grid-template-columns:
      repeat(
        4,
        minmax(0,1fr)
      );
  }
}

@media (max-width: 640px) {
  .pixel-garden-header {
    flex-direction: column;
  }

  .pixel-garden-total {
    width: 100%;
    min-width: 0;
    text-align: left;
  }

  .pixel-stats {
    grid-template-columns: 1fr;
  }

  .pixel-timeline {
    grid-template-columns:
      repeat(
        2,
        minmax(0,1fr)
      );
  }

  .pixel-progress-labels {
    align-items: flex-start;
    flex-direction: column;
  }

  .pixel-tree {
    min-height: 220px;
  }
}
</style>
