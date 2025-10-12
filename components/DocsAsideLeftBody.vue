<script setup lang="ts">
import { useAsyncData } from '#imports'
import { queryCollectionNavigation } from '#imports'
import { useRoute } from 'vue-router'

const route = useRoute()

// Haal navigatie voor jouw collectie op
const { data: navigation } = await useAsyncData('navigation', () => {
  // Veronderstel dat jouw collectie “content” heet
  return queryCollectionNavigation('content')
})

// navigation is nu de volledige navigatieboom; filter de relevante subset als nodig
console.log('DocsAsideLeftBody override geladen')
</script>

<template>
  <nav>
    <ul>
      <li v-for="item in navigation" :key="item.path">
        <NuxtLink :to="item.path">{{ item.title }}</NuxtLink>
        <ul v-if="item.children">
          <li v-for="child in item.children" :key="child.path">
            <NuxtLink :to="child.path">{{ child.title }}</NuxtLink>
          </li>
        </ul>
      </li>
    </ul>
  </nav>
</template>
