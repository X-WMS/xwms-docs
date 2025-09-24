import { defineNuxtConfig } from 'nuxt/config'

export default defineNuxtConfig({
  extends: ['@nuxt-themes/docus'],
  modules: ['@nuxt/content'],

  devtools: {
    enabled: false,
  }
})