<template>
   <div class="example">
      {{ message }}
   </div>
</template>

<script>
   import Vue from "vue";

   export default Vue.extend({
      data() {
         return {
            message: 'hello'
         }
      }
   });
</script>

<style lang="scss">
   .example {
      color: red;
   }
</style>