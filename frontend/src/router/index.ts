import { createRouter, createWebHistory } from 'vue-router'
import NotesView from '../views/NotesView.vue'
import LabelsView from '../views/LabelsView.vue'
import PomodoroView from '../views/PomodoroView.vue'
import StatisticsView from '../views/StatisticsView.vue'
import ProfileView from '../views/ProfileView.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/notes' },
    { path: '/notes', component: NotesView, props: { scope: 'active' } },
    { path: '/archived', component: NotesView, props: { scope: 'archived' } },
    { path: '/trash', component: NotesView, props: { scope: 'trash' } },
    { path: '/labels', component: LabelsView },
    { path: '/pomodoro', component: PomodoroView },
    { path: '/statistics', component: StatisticsView },
    { path: '/profile', component: ProfileView },
  ],
})
