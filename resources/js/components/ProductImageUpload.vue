<template>
    <div>
        <vue-dropzone ref="myVueDropzone" :id="id" :options="dropzoneOptions"
                      @vdropzone-success="uploaded" class="fieldset-disabled-hide"></vue-dropzone>
        <slot />
    </div>
</template>

<script>
    import vue2Dropzone from 'vue2-dropzone';

    export default {
        components: {
            vueDropzone: vue2Dropzone,
        },
        props: {
            uploadUrl: {type: String, required: true},
            max: {type: Number, default: null},
            maxSize: {type: Number, default: null},
            message: {type: String},
        },
        data() {
            return {
                id: this.getId(),
                dropzoneOptions: {
                    url: this.uploadUrl,
                    createImageThumbnails: false,
                    maxFilesize: this.maxSize || 2,
                    dictDefaultMessage: this.message || 'Drop files here to upload',
                    maxFiles: this.max || null,
                    headers: {
                        'X-CSRF-TOKEN': document.head.querySelector('meta[name="csrf-token"]').content
                    },
                    acceptedFiles: 'image/*',
                },
                images: [],
            }
        },
        methods: {
            uploaded(file, response) {
                let image = {
                    id: response.image.id,
                    key: this.getId(),
                    file: response.image.file,
                    alt: null,
                    attribute_key: '',
                };

                this.images.push(image);

                this.$emit('uploaded', image);
            },
            getId() {
                return Math.floor((1 + Math.random()) * 0x10000).toString(16).substring(1);
            }
        }
    }
</script>
