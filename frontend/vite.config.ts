import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],

  server: {
    host: '0.0.0.0',
    port: 5173,
    allowedHosts: ['harpocrate.falchero.fr'],
    strictPort: true,
  },

  test: {
    environment: 'node',

    coverage: {
      provider: 'v8',
      reporter: ['text'],
      include: [
        'src/**/*.ts',
        'src/**/*.vue',
      ],
      exclude: [
        'src/**/*.d.ts',
      ],
    },
  },
})
