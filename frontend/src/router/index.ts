import {
  createRouter,
  createWebHistory,
} from 'vue-router'

const NotesView =
  () => import(
    '../views/NotesView.vue'
  )

const NoteView =
  () => import(
    '../views/NoteView.vue'
  )

const TagsView =
  () => import(
    '../views/TagsView.vue'
  )

const PomodoroView =
  () => import(
    '../views/PomodoroView.vue'
  )

const StatisticsView =
  () => import(
    '../views/StatisticsView.vue'
  )

const TaskView =
  () => import(
    '../views/TaskView.vue'
  )

const SearchView =
  () => import(
    '../views/SearchView.vue'
  )

const ProfileView =
  () => import(
    '../views/ProfileView.vue'
  )

const ProjectsView =
  () => import(
    '../views/ProjectsView.vue'
  )

const ProjectBoardView =
  () => import(
    '../views/ProjectBoardView.vue'
  )

export const router = createRouter({
  history: createWebHistory(),

  scrollBehavior(
    _to,
    _from,
    savedPosition,
  ) {
    if (savedPosition) {
      return savedPosition
    }

    return {
      top: 0,
    }
  },

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
      path: '/notes/:id(\\d+)',
      component: NoteView,
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
      path:
        '/projects/:projectId(\\d+)/tasks/:id(\\d+)',
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
