import React from "react";
import style from './General.scss'
import CSVReader from 'react-csv-reader'

import TextInput from "../components/TextInput";

const { __ } = window.wp.i18n;

export default function General(props) {

    const {config} = props;
    const {general} = config;
    const {title} = general;
    return (<div className={style.test_class}>
        <label>{__('Select a CSV file','acowebs-plugin-boiler-plate-text-domain')}</label>
        <div className={style.uploader}>
            <CSVReader onFileLoaded={props.csvUploadHandler} />
        </div>
        <button onClick={props.handleUpdate}>{__('Process  CSV', 'masterstudy-import-csv')}</button>


    </div>)

}


