import React from "react";
import style from './General.scss'
import CSVReader from 'react-csv-reader'

import TextInput from "../components/TextInput";

const { __ } = window.wp.i18n;



export default function General(props) {


    const {config} = props;
    const {general} = config;
    const {title} = general;

    const options = {
        'single_choice_question': 'Single Choice Question', 
        'item_match_question': 'Item Match Question'
    }
    
    return (<div className={style.test_class}>
        <div className={style.form_froup}>
            <label>{__('Select Question Type', 'masterstudy-import-csv')}</label>
            <TextInput
                type="select"
                options={options}
            />
        </div>
        <label>{__('Select a CSV file','masterstudy-import-csv')}</label>

        <div className={style.uploader}>
            <CSVReader onFileLoaded={props.csvUploadHandler} />
        </div>
        <button onClick={props.handleUpdate}>{__('Process  CSV', 'masterstudy-import-csv')}</button>


    </div>)

}


