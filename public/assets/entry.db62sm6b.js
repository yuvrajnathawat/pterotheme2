import{ef as q}from"/assets/entry.xjehe85a.js";import{Qj as j}from"/assets/entry.fqyvsr1c.js";var G=async(z,A,B)=>{let{data:c}=await j.put(`/api/client/servers/${z}/startup/variable`,{key:A,value:B});return[q(c),c.meta.startup_command]};
export{G as qe};
