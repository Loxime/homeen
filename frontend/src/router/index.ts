import {
  createRouter,
  createWebHistory,
} from 'vue-router'

import NotesView from '../views/NotesView.vue'
import LibraryView from '../views/LibraryView.vue'
import PomodoroView from '../views/PomodoroView.vue'
import StatisticsView from '../views/StatisticsView.vue'
import ProfileView from '../views/ProfileView.vue'
import ChannelsView from '../views/ChannelsView.vue'
import ChannelView from '../views/ChannelView.vue'

export const router = createRouter({
  history: createWebHistory(),

  routes: [
    {
      path: '/',
      redirect: '/notes',
    },

    {
      path: '/notes',
      component: NotesView,
      props: {
        scope: 'active',
      },
    },

    {
      path: '/library',
      component: LibraryView,
    },

    {
      path: '/archived',
      component: NotesView,
      props: {
        scope: 'archived',
      },
    },

    {
      path: '/trash',
      component: NotesView,
      props: {
        scope: 'trash',
      },
    },

    {
      path: '/labels',
      redirect: '/notes',
    },

    {
      path: '/pomodoro',
      component: PomodoroView,
    },

    {
      path: '/statistics',
      component: StatisticsView,
    },

    {
      path: '/channels',
      component: ChannelsView,
    },

    {
      path: '/canal/:code(\\d{9})',
      component: ChannelView,
    },

    {
      path: '/profile',
      component: ProfileView,
    },
  ],
})
