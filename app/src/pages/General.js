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
        'item_match_question': 'Item Match Question', 
        'multi_choice': 'Multi Choice Question', 
        'fill_the_gaps': 'Fill The Gap Question'
    }
    
    return (<div className={style.test_class}>
        {/* Left Part */}
       <div>
            <div className={style.form_froup}>
                <label>{__('Select Question Type', 'masterstudy-import-csv')}</label>
                <TextInput
                    type="select"
                    options={options}
                    onChange={props.onChangeHandler}
                />
            </div>
            <label>{__('Select a CSV file','masterstudy-import-csv')}</label>

            <div className={style.uploader}>
                <CSVReader onFileLoaded={props.csvUploadHandler} />
            </div>
            {
                props.upload_complete ? <div className={style.msgS}>
                    <span>{__('CSV upload complete.', 'masterstudy-import-csv')}</span>
                </div> : null
            }
            <button onClick={props.handleUpdate}>{__('Process  CSV', 'masterstudy-import-csv')}</button>

       </div>

       {/* Right Part */}
       <div>
            <div className={style.right_inner}>
                <h3>{__('Download CSV Sample', 'masterstudy-import-csv')}</h3>
                <a href={props.assets_url + '/csv/Single_Choice_Quiz_Template.csv'} download >{__('Single Choice Question', 'masterstudy-import-csv')}</a>
                <a href={props.assets_url + '/csv/Multi_Choice_Quiz_Template.csv'} download >{__('Multi Choice Question', 'masterstudy-import-csv')}</a>
                <a href={props.assets_url + '/csv/item_matchs_Quiz_Template.csv'} download >{__('Item Match Question', 'masterstudy-import-csv')}</a>
                <a href={props.assets_url + '/csv/Fill_The_Gap_Quiz_Template.csv'} download >{__('Fill the Gap Question', 'masterstudy-import-csv')}</a>
            </div>
       </div>
    </div>)

}


