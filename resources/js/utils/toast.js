import {useToast} from 'vue-toast-notification';
import 'vue-toast-notification/dist/theme-sugar.css';

const toast = useToast();

let options = {
    position: "top-right",
}

export const toastAlert = (type = "default", message = "") => {
    const types = ["success", "error", "info", "warning", "default"];

    if (!types.includes(type)) type = "default";

    toast[type](message, options); // calls toast.success, toast.error, etc.
};
