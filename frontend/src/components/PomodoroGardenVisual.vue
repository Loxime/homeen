<script setup lang="ts">
defineProps<{
  stage:
    | 'roots'
    | 'sprout'
    | 'sapling'
    | 'tree'
    | 'forest'
}>()
</script>

<template>
  <div class="pomodoro-garden-2d">
    <svg
      viewBox="0 0 420 260"
      role="img"
      :aria-label="`Progression Pomodoro : ${stage}`"
    >
      <defs>
        <linearGradient
          id="sky"
          x1="0"
          y1="0"
          x2="0"
          y2="1"
        >
          <stop
            offset="0%"
            stop-color="#dff3ff"
          />

          <stop
            offset="100%"
            stop-color="#f5fbff"
          />
        </linearGradient>

        <linearGradient
          id="grass"
          x1="0"
          y1="0"
          x2="0"
          y2="1"
        >
          <stop
            offset="0%"
            stop-color="#76c77a"
          />

          <stop
            offset="100%"
            stop-color="#4f9f59"
          />
        </linearGradient>

        <radialGradient id="leaf">
          <stop
            offset="0%"
            stop-color="#9be184"
          />

          <stop
            offset="100%"
            stop-color="#3d9f50"
          />
        </radialGradient>
      </defs>

      <rect
        width="420"
        height="260"
        rx="26"
        fill="url(#sky)"
      />

      <circle
        cx="350"
        cy="52"
        r="24"
        fill="#ffd85c"
      />

      <g
        fill="#fff"
        opacity=".85"
      >
        <ellipse
          cx="75"
          cy="60"
          rx="34"
          ry="14"
        />

        <ellipse
          cx="100"
          cy="58"
          rx="28"
          ry="19"
        />

        <ellipse
          cx="122"
          cy="62"
          rx="25"
          ry="12"
        />
      </g>

      <path
        d="
          M0 185
          Q90 125 190 180
          Q280 125 420 170
          V260
          H0
          Z
        "
        fill="#a9d9a8"
      />

      <path
        d="
          M0 205
          Q120 160 235 205
          Q330 165 420 195
          V260
          H0
          Z
        "
        fill="url(#grass)"
      />

      <!-- Racines -->

      <g v-if="stage === 'roots'">
        <ellipse
          cx="210"
          cy="202"
          rx="14"
          ry="9"
          fill="#805238"
          transform="rotate(-15 210 202)"
        />

        <g
          fill="none"
          stroke="#805238"
          stroke-width="4"
          stroke-linecap="round"
        >
          <path d="M210 205 Q180 218 162 238" />
          <path d="M210 205 Q235 220 255 240" />
          <path d="M210 205 Q208 228 208 248" />
        </g>
      </g>

      <!-- Pousse -->

      <g v-if="stage === 'sprout'">
        <path
          d="M210 207 Q209 170 210 133"
          fill="none"
          stroke="#3f9f50"
          stroke-width="8"
          stroke-linecap="round"
        />

        <ellipse
          cx="187"
          cy="159"
          rx="27"
          ry="14"
          fill="url(#leaf)"
          transform="rotate(25 187 159)"
        />

        <ellipse
          cx="232"
          cy="142"
          rx="27"
          ry="14"
          fill="url(#leaf)"
          transform="rotate(-25 232 142)"
        />
      </g>

      <!-- Jeune arbre -->

      <g v-if="stage === 'sapling'">
        <rect
          x="202"
          y="142"
          width="16"
          height="68"
          rx="7"
          fill="#805238"
        />

        <circle
          cx="210"
          cy="118"
          r="43"
          fill="url(#leaf)"
        />

        <circle
          cx="178"
          cy="137"
          r="31"
          fill="url(#leaf)"
        />

        <circle
          cx="241"
          cy="136"
          r="31"
          fill="url(#leaf)"
        />
      </g>

      <!-- Arbre -->

      <g v-if="stage === 'tree'">
        <ellipse
          cx="210"
          cy="211"
          rx="62"
          ry="12"
          fill="#397746"
          opacity=".22"
        />

        <rect
          x="198"
          y="124"
          width="24"
          height="88"
          rx="8"
          fill="#805238"
        />

        <circle
          cx="210"
          cy="92"
          r="58"
          fill="url(#leaf)"
        />

        <circle
          cx="162"
          cy="119"
          r="42"
          fill="url(#leaf)"
        />

        <circle
          cx="258"
          cy="119"
          r="42"
          fill="url(#leaf)"
        />

        <circle
          cx="178"
          cy="78"
          r="37"
          fill="url(#leaf)"
        />

        <circle
          cx="244"
          cy="77"
          r="37"
          fill="url(#leaf)"
        />
      </g>

      <!-- Forêt -->

      <g v-if="stage === 'forest'">
        <g transform="translate(65 52) scale(.72)">
          <rect
            x="94"
            y="115"
            width="20"
            height="86"
            rx="7"
            fill="#805238"
          />

          <circle
            cx="104"
            cy="92"
            r="55"
            fill="url(#leaf)"
          />
        </g>

        <g transform="translate(105 10)">
          <rect
            x="94"
            y="115"
            width="22"
            height="98"
            rx="7"
            fill="#805238"
          />

          <circle
            cx="105"
            cy="87"
            r="66"
            fill="url(#leaf)"
          />
        </g>

        <g transform="translate(220 48) scale(.78)">
          <rect
            x="94"
            y="115"
            width="20"
            height="86"
            rx="7"
            fill="#805238"
          />

          <circle
            cx="104"
            cy="92"
            r="55"
            fill="url(#leaf)"
          />
        </g>
      </g>

      <g>
        <circle
          cx="60"
          cy="221"
          r="4"
          fill="#fff"
        />

        <circle
          cx="60"
          cy="221"
          r="2"
          fill="#fbbc04"
        />

        <circle
          cx="345"
          cy="225"
          r="4"
          fill="#fff"
        />

        <circle
          cx="345"
          cy="225"
          r="2"
          fill="#ea4335"
        />
      </g>
    </svg>
  </div>
</template>

<style scoped>
.pomodoro-garden-2d {
  width: 100%;
  min-width: 0;
}

.pomodoro-garden-2d svg {
  display: block;
  width: 100%;
  height: auto;
  border-radius: 24px;
  box-shadow:
    inset 0 0 0 1px
    rgba(60,64,67,.08);
}

@media (max-width: 640px) {
  .pomodoro-garden-2d svg {
    border-radius: 18px;
  }
}
</style>
