<script>
import vueDropzone from "vue2-dropzone";

export default {
  data: () => ({
    dropOptions: {
      url: '/admin/module/livex'
    }
  }),
  components: {
    vueDropzone
  }
};
</script>