package com.jiraws.library.book.Entities;

import jakarta.persistence.*;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.NoArgsConstructor;
import lombok.extern.slf4j.Slf4j;

@Entity
@Table(name = "books")
@AllArgsConstructor
@NoArgsConstructor
@Builder
@Slf4j
public class BookEntity {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id ;
    private String isbn;
    private String name;
    private String description;
    private Integer year;
    private Integer pages;

}
