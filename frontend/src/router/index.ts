import {
  createRouter,
  createWebHistory,
} from 'vue-router'

import NotesView from '../views/NotesView.vue'
import TagsView from '../views/TagsView.vue'
import PomodoroView from '../views/PomodoroView.vue'
import StatisticsView from '../views/StatisticsView.vue'
import TaskView from '../views/TaskView.vue'
import SearchView from '../views/SearchView.vue'
import ProfileView from '../views/ProfileView.vue'
import ProjectsView from '../views/ProjectsView.vue'
import ProjectBoardView from '../views/ProjectBoardView.vue'

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
      redirect: '/notes',
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
      path: '/tags',
      component: TagsView,
    },

    {
      path: '/labels',
      redirect: '/tags',
    },

    {
      path: '/search',
      component: SearchView,
    },

    {
      path: '/tasks/:id(\\d+)',
      component: TaskView,
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
      path: '/projects',
      component: ProjectsView,
    },

    {
      path: '/projects/:id(\\d+)',
      component: ProjectBoardView,
    },

    {
      path: '/channels',
      redirect: '/projects',
    },

    {
      path: '/canal/:code(\\d{9})',
      redirect: '/projects',
    },

    {
      path: '/profile',
      component: ProfileView,
    },
  ],
})
